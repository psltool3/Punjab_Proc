#!/usr/bin/python
# -*- coding: utf-8 -*-

import math
import numpy as np
import json
import pickle
import os.path
import shutil
import subprocess
import uuid
import pandas as pd
from pulp import *
import excelrd
from flask import Flask, request, jsonify
from flask_cors import CORS
import mysql.connector
import requests
import time
import secrets
import string
from datetime import datetime
from pulp import LpStatus, LpStatusInfeasible, LpStatusUnbounded, LpStatusNotSolved, LpStatusUndefined
import msoffcrypto # Install this in requirement using 'pip install msoffcrypto-tool' & 'pip install xlrd'
from io import BytesIO
from cryptography.fernet import Fernet	
from datetime import datetime
import multiprocessing
import sqlite3
import traceback
import time

auth_token = None
token_timestamp = 0
TOKEN_VALIDITY_SECONDS = 600 


app = Flask(__name__)
CORS(app)

#CORS(app, resources={r"/": {"origins": ""}})

UPLOAD_FOLDER = 'Backend'
ALLOWED_EXTENSIONS = {'xlsx', 'xls'}

app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER
stop_process = False

def count_distinct_months(input_str):
    months_list = [month.strip() for month in input_str.split(',')]
    unique_months_count = len(set(months_list))
    return unique_months_count

def generate_random_id(length=14):
    alphabet = string.ascii_letters + string.digits
    random_id = ''.join(secrets.choice(alphabet) for _ in range(length))
    return random_id

def connect_to_database():
    host = 'localhost'
    user = 'root'
    password = ''
    database = 'punjab_proc'
    connection = mysql.connector.connect(
        host=host, user=user, password=password, database=database
    )
    return connection


def allowed_file(filename):
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS


@app.route('/')
def hello():
    return 'Hi, PDS!'

# CHANGE: simple job store in SQLite so long-running
# requests can run in background and be resumed/polled.
JOB_DB_PATH = os.path.join(os.path.dirname(__file__), "jobs.db")
# CHANGE: unique id for this Python process; lets us detect
# jobs that were started before a server restart.
SERVER_INSTANCE_ID = str(uuid.uuid4())

def _job_db_connect():
    con = sqlite3.connect(JOB_DB_PATH, check_same_thread=False)
    con.row_factory = sqlite3.Row
    return con

def _job_db_init():
    con = _job_db_connect()
    try:
        con.execute(
            """
            CREATE TABLE IF NOT EXISTS jobs (
                job_id TEXT PRIMARY KEY,
                client_id TEXT,
                endpoint TEXT,
                status TEXT,
                message TEXT,
                created_at TEXT,
                updated_at TEXT,
                result_json TEXT,
                error TEXT
            )
            """
        )
        # CHANGE: lightweight migration for older DBs created
        # before we added server_instance_id.
        cols = [r["name"] for r in con.execute("PRAGMA table_info(jobs)").fetchall()]
        if "server_instance_id" not in cols:
            con.execute("ALTER TABLE jobs ADD COLUMN server_instance_id TEXT")
        con.execute("CREATE INDEX IF NOT EXISTS idx_jobs_client_status ON jobs(client_id, status)")
        con.execute("CREATE INDEX IF NOT EXISTS idx_jobs_instance_status ON jobs(server_instance_id, status)")
        con.commit()
    finally:
        con.close()

def _job_prune_old(days: int = 30):
    """
    CHANGE: keep jobs.db from growing forever by removing
    completed/failed jobs older than N days (default 30).
    """
    con = _job_db_connect()
    try:
        # SQLite doesn't have DATEADD; we store timestamps as ISO strings,
        # so compare lexicographically against a computed cutoff.
        from datetime import timedelta, timezone  # local import to avoid touching global imports
        cutoff = (datetime.now(timezone.utc) - timedelta(days=days)).strftime("%Y-%m-%dT%H:%M:%SZ")
        con.execute(
            "DELETE FROM jobs WHERE status IN ('completed','failed') AND updated_at < ?",
            (cutoff,),
        )
        con.commit()
    finally:
        con.close()

def _job_reconcile_after_restart():
    """
    CHANGE: on server restart, mark any old 'queued'/'running'
    jobs from previous instances as failed, so the UI doesn't
    poll forever for work that can never finish.
    """
    con = _job_db_connect()
    try:
        ts = _job_now_iso()
        con.execute(
            """
            UPDATE jobs
            SET status='failed',
                message='server restarted',
                updated_at=?,
                error=COALESCE(error,'') || CASE WHEN error IS NULL OR error='' THEN '' ELSE '\n' END || 'Server restarted; background worker no longer running.'
            WHERE status IN ('queued','running')
              AND (server_instance_id IS NULL OR server_instance_id != ?)
            """,
            (ts, SERVER_INSTANCE_ID),
        )
        con.commit()
    finally:
        con.close()

def _job_now_iso():
    # Use timezone-aware UTC datetime (utcnow() is deprecated).
    from datetime import timezone
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")

def _job_create(client_id: str, endpoint: str, message: str = "queued") -> str:
    job_id = str(uuid.uuid4())
    ts = _job_now_iso()
    con = _job_db_connect()
    try:
        con.execute(
            # CHANGE: store server_instance_id so we know
            # which process owns this job.
            "INSERT INTO jobs(job_id, client_id, endpoint, server_instance_id, status, message, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?)",
            (job_id, client_id, endpoint, SERVER_INSTANCE_ID, "queued", message, ts, ts),
        )
        con.commit()
    finally:
        con.close()
    return job_id

def _job_update(job_id: str, *, status: str = None, message: str = None, result_json: str = None, error: str = None):
    fields = []
    values = []
    if status is not None:
        fields.append("status=?")
        values.append(status)
    if message is not None:
        fields.append("message=?")
        values.append(message)
    if result_json is not None:
        fields.append("result_json=?")
        values.append(result_json)
    if error is not None:
        fields.append("error=?")
        values.append(error)
    fields.append("updated_at=?")
    values.append(_job_now_iso())
    values.append(job_id)
    con = _job_db_connect()
    try:
        con.execute(f"UPDATE jobs SET {', '.join(fields)} WHERE job_id=?", values)
        con.commit()
    finally:
        con.close()

def _job_get(job_id: str):
    con = _job_db_connect()
    try:
        cur = con.execute("SELECT * FROM jobs WHERE job_id=?", (job_id,))
        row = cur.fetchone()
        return dict(row) if row else None
    finally:
        con.close()

def _job_get_active_for_client(client_id: str, endpoint: str = None):
    con = _job_db_connect()
    try:
        if endpoint:
            cur = con.execute(
                "SELECT * FROM jobs WHERE client_id=? AND endpoint=? AND status IN ('queued','running') ORDER BY created_at DESC LIMIT 1",
                (client_id, endpoint),
            )
        else:
            cur = con.execute(
                "SELECT * FROM jobs WHERE client_id=? AND status IN ('queued','running') ORDER BY created_at DESC LIMIT 1",
                (client_id,),
            )
        row = cur.fetchone()
        return dict(row) if row else None
    finally:
        con.close()

_job_db_init()
_job_reconcile_after_restart()
# CHANGE: prune old finished jobs so jobs.db size stays bounded.
_job_prune_old(days=30)

@app.route('/job_status/<job_id>', methods=['GET'])
def job_status(job_id):
    job = _job_get(job_id)
    if not job:
        return jsonify({"status": 0, "message": "job not found", "job_id": job_id}), 404
    # keep response compatible with your existing style: status=1 means ok
    return jsonify({"status": 1, "job": job})

@app.route('/job_result/<job_id>', methods=['GET'])
def job_result(job_id):
    job = _job_get(job_id)
    if not job:
        return jsonify({"status": 0, "message": "job not found", "job_id": job_id}), 404
    if job.get("status") != "completed":
        return jsonify({"status": 0, "message": "job not completed", "job_id": job_id, "job_status": job.get("status")}), 409
    # result_json is stored as text (already JSON string from your original endpoint)
    return app.response_class(job.get("result_json") or "", mimetype="application/json")

@app.route('/active_job', methods=['GET'])
def active_job():
    client_id = request.args.get("client_id") or request.args.get("user") or ""
    endpoint = request.args.get("endpoint")  # optional
    if not client_id:
        return jsonify({"status": 0, "message": "client_id is required"}), 400
    job = _job_get_active_for_client(client_id, endpoint=endpoint)
    return jsonify({"status": 1, "job": job})

def _run_processfile_in_background(job_id: str, form_data: dict):
    try:
        _job_update(job_id, status="running", message="processing started")
        # CHANGE: re-enter existing synchronous processFile()
        # in a fresh request context so we can reuse all the
        # original logic without duplicating it.
        safe_data = dict(form_data or {})
        safe_data["async"] = "0"
        with app.test_request_context('/processFile', method='POST', data=safe_data):
            resp = processFile()
        # Flask may return (response, code) tuples or Response
        # objects; normalize to plain text JSON string.
        if isinstance(resp, tuple):
            resp = resp[0]
        result_text = resp.get_data(as_text=True) if hasattr(resp, "get_data") else str(resp)
        _job_update(job_id, status="completed", message="processing completed", result_json=result_text)
    except Exception:
        _job_update(job_id, status="failed", message="processing failed", error=traceback.format_exc())

def _run_processfileLeg1_in_background(job_id: str, form_data: dict):
    try:
        _job_update(job_id, status="running", message="processing started")
        # Mirror of _run_processfile_in_background for the Leg1 route.
        # Re-enter the synchronous processFile_leg1() in a fresh request context.
        safe_data = dict(form_data or {})
        safe_data["async"] = "0"
        with app.test_request_context('/processFileleg1', method='POST', data=safe_data):
            resp = processFile_leg1()
        if isinstance(resp, tuple):
            resp = resp[0]
        result_text = resp.get_data(as_text=True) if hasattr(resp, "get_data") else str(resp)
        _job_update(job_id, status="completed", message="processing completed", result_json=result_text)
    except Exception:
        _job_update(job_id, status="failed", message="processing failed", error=traceback.format_exc())

    
def read_protected_excel(file_path, password, sheet_name=None):
    with open(file_path, 'rb') as file:
        file_decryptor = msoffcrypto.OfficeFile(file)
        file_decryptor.load_key(password=password)  # Provide the password here

        # Create a BytesIO buffer to store the decrypted content
        decrypted = BytesIO()
        file_decryptor.decrypt(decrypted)

        # Read the specified sheet or all sheets from the decrypted content
        dfs = pd.read_excel(decrypted, sheet_name=sheet_name, engine='openpyxl')

    return dfs        
    
def write_log(message, log_directory='logs'):
    # Ensure log directory exists
    if not os.path.exists(log_directory):
        os.makedirs(log_directory, mode=0o755, exist_ok=True)

    # Get current year, month, and day
    now = datetime.now()
    year = now.strftime('%Y')
    month = now.strftime('%m')
    day = now.strftime('%d')

    # Construct the directory structure (year/month)
    year_directory = os.path.join(log_directory, year)
    month_directory = os.path.join(year_directory, month)

    os.makedirs(year_directory, mode=0o755, exist_ok=True)
    os.makedirs(month_directory, mode=0o755, exist_ok=True)

    # Define the log file path (year/month/day.log)
    log_file_path = os.path.join(month_directory, f"{day}.log")

    # Format the log message with a timestamp
    timestamp = now.strftime('%Y-%m-%d %H:%M:%S')
    formatted_message = f"[{timestamp}] {message}\n"

    # Write the log message to the file
    with open(log_file_path, 'a') as log_file:
        log_file.write(formatted_message)    
        
    


@app.route('/get_users', methods=['GET'])
def get_users():
    if request.method == 'GET':
        connection = connect_to_database()
        user_list = []

        if connection.is_connected():
            cursor = connection.cursor()
            query = 'SELECT * FROM login WHERE 1'
            cursor.execute(query)
            user = cursor.fetchall()
            connection.close()

            if user:
                for row in user:
                    temp = {'username': row[0], 'password': row[1], '_id': row[2]}
                    user_list.append(temp)
                return jsonify(user_list)
            else:
                return jsonify(user_list)
        else:
            return jsonify(user_list)


@app.route('/extract_db', methods=['POST'])
def extract_db():
    if request.method == 'POST':
        connection = connect_to_database()
        warehouse_data = []
        fps_data = []
        all_data = {}

        if connection.is_connected():

            # ================= FETCH PC DATA =================
            cursor = connection.cursor()
            query = "SELECT * FROM pc WHERE active='1'"
            cursor.execute(query)
            user = cursor.fetchall()

            if user:
                for row in user:
                    temp = {
                        'State Name': 'Punjab',
                        'PC_District': row[1] if row[1] else 'Amritsar',
                        'PC_Name': row[2] if row[2] else 'Default PC',
                        'PC_ID': row[6] if row[6] else 0,
                        'PC_Lat': row[3] if row[3] else 31.6340,
                        'PC_Long': row[4] if row[4] else 74.8723,
                        'Paddy_Procure': row[7] if row[7] else 0,
                        'Milling_Centre_P': row[8] if row[8] else 'amritsar'
                    }
                    warehouse_data.append(temp)

            # ================= FETCH MILL DATA =================
            cursor = connection.cursor()
            query = "SELECT * FROM mill WHERE active='1'"
            cursor.execute(query)
            user = cursor.fetchall()

            connection.close()

            if user:
                for row in user:
                    temp = {
                        'State Name': 'Punjab',
                        'Mill_District': row[0] if row[0] else 'Amritsar',
                        'Mill_Name': row[1] if row[1] else 'Default Mill',
                        'Mill_ID': row[2] if row[2] else 0,
                        'Mill_Lat': row[3] if row[3] else 31.6340,
                        'Mill_Long': row[4] if row[4] else 74.8723,
                        'Milling_Centre_M': row[5] if row[5] else 'amritsar',
                        'Milling_Capacity': row[6] if row[6] else 0
                    }
                    fps_data.append(temp)

            all_data["warehouse"] = warehouse_data
            all_data["fps"] = fps_data

        else:
            # ================= DEFAULT DATA IF DB CONNECTION FAILS =================
            all_data["warehouse"] = [{
                'State Name': 'Punjab',
                'PC_District': 'Amritsar',
                'PC_Name': 'Default PC',
                'PC_ID': 0,
                'PC_Lat': 31.6340,
                'PC_Long': 74.8723,
                'Paddy_Procure': 0,
                'Milling_Centre_P': 'amritsar'
            }]

            all_data["fps"] = [{
                'State Name': 'Punjab',
                'Mill_District': 'Amritsar',
                'Mill_Name': 'Default Mill',
                'Mill_ID': 0,
                'Mill_Lat': 31.6340,
                'Mill_Long': 74.8723,
                'Milling_Centre_M': 'amritsar',
                'Milling_Capacity': 0
            }]

        # ================= CREATE DATAFRAMES =================
        wh = pd.DataFrame(all_data['warehouse'])
        fps = pd.DataFrame(all_data['fps'])

        wh = wh.loc[:, [
            "State Name",
            "PC_District",
            "PC_Name",
            "PC_ID",
            "PC_Lat",
            "PC_Long",
            "Paddy_Procure",
            "Milling_Centre_P"
        ]]

        fps = fps.loc[:, [
            "State Name",
            "Mill_District",
            "Mill_Name",
            "Mill_ID",
            "Mill_Lat",
            "Mill_Long",
            "Milling_Capacity",
            "Milling_Centre_M"
        ]]

        # ================= CONVERT IDS TO NUMERIC =================
        def convert_to_numeric(value):
            try:
                return pd.to_numeric(value)
            except (ValueError, TypeError):
                return value

        wh['PC_ID'] = wh['PC_ID'].apply(convert_to_numeric)
        fps['Mill_ID'] = fps['Mill_ID'].apply(convert_to_numeric)

        # ================= EXPORT TO EXCEL =================
        with pd.ExcelWriter('Backend//Data_1.xlsx') as writer:
            wh.to_excel(writer, sheet_name='A.1 Warehouse', index=False)
            fps.to_excel(writer, sheet_name='A.2 FPS', index=False)

        return {"success": 1}
        

        
@app.route('/extract_data', methods=['POST'])
def extract_data():
    if request.method == 'POST':
        try:
            connection = connect_to_database()
            data = []
            fci_data = []
            wb_data = []
            

            if not connection or not connection.is_connected():
                return {"success": 0, "message": "Database connection failed"}

            cursor = connection.cursor()

            # ================= FCI / MILL DATA =================
            cursor.execute("SELECT * FROM pc2 WHERE active='1'")
            mill_rows = cursor.fetchall()

            for row in mill_rows:
                fci_data.append({
                    'State Name': '',
                    'PC_District': row[1],
                    'PC_Name': row[2],
                    'PC_ID': row[3],
                    'PC_Lat': row[4],
                    'PC_Long': row[5],
                    'Storage_Point_P': row[6],
                    'Wheat_Procure': row[7]
                   
                })
                
                
            # ================= WB DATA =================
            cursor.execute("SELECT * FROM weighbridge WHERE active='1'")
            wb_rows = cursor.fetchall()

            for row in wb_rows:
                data.append({
                    'WB_District': row[0],
                    'WB_Name': row[1],
                    'WB_ID': row[2],
                    'WB_Lat': row[5],
                    'WB_Long': row[6],
                    'Storage_Point_WB': row[8],
                    
                })    

            

            # ================= WAREHOUSE DATA =================
            cursor.execute("SELECT * FROM warehouse WHERE active='1'")
            wh_rows = cursor.fetchall()

            for row in wh_rows:
                data.append({
                    'SW_District': row[0],
                    'SW_Name': row[1],
                    'SW_ID': row[2],
                    'SW_type': row[3],
                    'SW_Lat': row[5],
                    'SW_Long': row[6],
                    'Available_Capacity': row[9],
                    'Storage_Point_W': row[8],
                    
                })

            cursor.close()
            connection.close()

            # ================= DATAFRAMES =================
            import pandas as pd
            import os

            wh = pd.DataFrame(data)
            fci = pd.DataFrame(fci_data)
            wb = pd.DataFrame(wb_data)
            

            # ================= HANDLE EMPTY DATABASE =================
            if wh.empty:
                wh = pd.DataFrame([{
                    "SW_District": "Amritsar",
                    "SW_Name": "",
                    "SW_ID": 0,
                    'SW_Type': "ABC",
                    "SW_Lat": 0,
                    "SW_Long": 0,
                    'Available_Capacity': 0,
                    'Storage_Point_W': "PQR",
                    
                }])

            if fci.empty:
                fci = pd.DataFrame([{
                    "PC_District": "Amritsar",
                    "PC_Name": "",
                    "PC_ID": 0,
                    "PC_Lat": 0,
                    "PC_Long": 0,
                    "Wheat_Procure": 0,
                    "Storage_Point_P": "XYZ"
                }])
                
            if wb.empty:
                wb = pd.DataFrame([{
                    "WB_District": "Amritsar",
                    "WB_Name": "",
                    "WB_ID": 0,
                    "WB_Lat": 0,
                    "WB_Long": 0,
                    "Storage_Point_WB":"DEF"
                    
                }])    

            

            # ================= COLUMN SELECTION =================
            wh = wh.loc[:, [
                "SW_District", "SW_Name", "SW_ID", "SW_Lat", "SW_Type",
                "SW_Long", "Available_Capacity","Storage_Point_W", 
            ]]

            fci = fci.loc[:, [
                "PC_District", "PC_Name", "PC_ID", "PC_Agency_Name",
                "PC_Lat", "PC_Long", "Wheat_Procure","Storage_Point_P",
            ]]
            
            wb = wb.loc[:, [
                "WB_District", "WB_Name", "WB_ID", "WB_Lat", "WB_Long", "Storage_Point_WB"
            ]]

           

            # ================= CLEANING =================
            wh = wh.drop_duplicates()
            fci = fci.drop_duplicates()
            wb = wb.drop_duplicates()
            
            
            

           

            # ================= EXCEL EXPORT =================
            os.makedirs("Backend", exist_ok=True)
            with pd.ExcelWriter("Backend/Data_2.xlsx") as writer:
                wh.to_excel(writer, sheet_name="A.1 Warehouse", index=False)
                fci.to_excel(writer, sheet_name="A.2 FCI", index=False)
                wb.to_excel(writer, sheet_name="A.2 WB", index=False)
               

            # ================= FINAL RESPONSE =================
            return jsonify({"success": 1})

        except Exception as e:
            return {"success": 0, "message": str(e)}

    else:
        return {"success": 0, "message": "Invalid request method"}

        
@app.route('/fetchdatafromsql', methods=['GET'])        
def fetch_data_from_sql():
    if request.method == 'GET':
        connection = connect_to_database()
        if connection.is_connected():
            cursor = connection.cursor()
            query = "SELECT * FROM optimised_table"
            cursor.execute(query)
            data = cursor.fetchall()
            cursor.close()
            connection.close()
            df = pd.DataFrame(data, columns=['id', 'month', 'year','day', 'data', 'last_updated', 'rolled_out', 'cost'])
            df_first_4_columns = df[['id', 'month', 'year','day']]
            # Convert selected columns to JSON string
            json_data = df_first_4_columns.to_json(orient='records')
            return json_data
        else:
            print("Error: Unable to connect to the database")
            return jsonify({"error": "Unable to connect to the database"})
    else:
        return jsonify({"error": "Request method is not GET"})

@app.route('/uploadConfigExcel', methods=['POST'])
def upload_config_excel():
    data = {}
    try:
        file = request.files['uploadFile']
        if file and allowed_file(file.filename):
            file_path = os.path.join(app.config['UPLOAD_FOLDER'], 'Data_1.xlsx')
            os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)
            file.save(file_path)
            data['status'] = 1
            df = pd.read_excel(file_path)
        else:
            data['status'] = 0
            data['message'] = 'Invalid file. Only .xlsx or .xls files are allowed.'
    except Exception as e:
        data['status'] = 0
        data['message'] = 'Error uploading file'
        
        
    input = pd.ExcelFile('Backend//Data_1.xlsx')
    node1 = pd.read_excel(input,sheet_name="A.1 Warehouse")
    node2 = pd.read_excel(input,sheet_name="A.2 FPS")
    dist = [[0 for a in range(len(node2["FPS_ID"]))] for b in range(len(node1["WH_ID"]))]
    phi_1 = []
    phi_2 = []
    delta_phi = []
    delta_lambda = []
    R = 6371 

    for i in node1.index:
        for j in node2.index:
            phi_1=math.radians(node1["WH_Lat"][i])
            phi_2=math.radians(node2["FPS_Lat"][j])
            delta_phi=math.radians(node2["FPS_Lat"][j]-node1["WH_Lat"][i])
            delta_lambda=math.radians(node2["FPS_Long"][j]-node1["WH_Long"][i])
            delta_lambda=math.radians(node2["FPS_Long"][j]-node1["WH_Long"][i])
            x=math.sin(delta_phi / 2.0) ** 2 + math.cos(phi_1) * math.cos(phi_2) * math.sin(delta_lambda / 2.0) ** 2
            y=2 * math.atan2(math.sqrt(x), math.sqrt(1 - x))
            dist[i][j]=R*y
            
    dist=np.transpose(dist)
    df3 = pd.DataFrame(data = dist, index = node2['FPS_ID'], columns = node1['WH_ID'])
    df3.to_excel('Backend//Distance_Matrix.xlsx', index=True)
    return jsonify(data)



@app.route('/getfcidata', methods=['POST'])
def fci_data():
    try:
        usn = pd.ExcelFile('Backend//Data_1.xlsx')
        fci = pd.read_excel(usn, sheet_name='A.1 Warehouse', index_col=None)
        fps = pd.read_excel(usn, sheet_name='A.2 FPS', index_col=None)
        fps['Milling_Capacity'] = pd.to_numeric(fps['Milling_Capacity'], errors='coerce').fillna(0)
       
        warehouse_no = fci['PC_ID'].nunique()
        fps_no = fps['Mill_ID'].nunique()
        combined_districts = pd.concat([fci['PC_District'],fps['Mill_District']])
        districts_no = combined_districts.nunique()
        total_demand = float(fci['Paddy_Procure'].sum())
        
        
        milling_capacity = float(fps['Milling_Capacity'].sum())
        

        result = {
            'Warehouse_No': warehouse_no, 
            'FPS_No': fps_no, 
            'Total_Demand': total_demand,
            'District_Count': districts_no, 
            'Milling_Capacity': milling_capacity
        }
        return jsonify(result)
        
    except Exception as e:
        return jsonify({'status': 0, 'message': str(e)})

@app.route('/getfcidataleg1', methods=['POST'])
def fci_dataleg1():
    try:
        usn = pd.ExcelFile('Backend//Data_2.xlsx')
        wh = pd.read_excel(usn, sheet_name='A.1 Warehouse', index_col=None)
        fci = pd.read_excel(usn, sheet_name='A.2 FCI', index_col=None)
        wb = pd.read_excel(usn, sheet_name='A.2 WB', index_col=None)

        warehouse_no = fci['PC_ID'].nunique()
        fps_no = wh["SW_ID"].nunique()
        wb_no = wb["WB_ID"].nunique()
        
        combined_districts = pd.concat([fci['PC_District'],wh['SW_District'],wb['WB_District']])
        districts_no = combined_districts.nunique()
        
        total_demand_mota = float(wh['Available_Capacity'].sum())
        total_supply_mota = float(fci['Wheat_Procure'].sum())
        
        combined_centres = pd.concat([fci['Storage_Point_P'],wh['Storage_Point_W'],wb['Storage_Point_WB']])
        centres_no = combined_centres.nunique()
        
        result = {'Warehouse_No': warehouse_no, 'FPS_No': fps_no, 'Total_Demand Mota': total_demand_mota, 'Total_Supply_Mota': total_supply_mota, 'District_Count': districts_no,'WB_No': wb_no,'Centre_Count': centres_no,}
       
        
        
        return jsonify(result)
    except Exception as e:
        return jsonify({'status': 0, 'message': str(e)})


@app.route('/getGraphData', methods=['POST'])
def graph_data():
    try:
        usn = pd.ExcelFile('Backend//Data_1.xlsx')
        FCI = pd.read_excel(usn, sheet_name='A.1 Warehouse', index_col=None)
        FPS = pd.read_excel(usn, sheet_name='A.2 FPS', index_col=None)
        FPS['Milling_Capacity'] = pd.to_numeric(FPS['Milling_Capacity'], errors='coerce').fillna(0)


        
        District_Capacity = {}
        for i in range(len(FCI["PC_District"])):
            District_Name = FCI["PC_District"][i]
            if District_Name not in District_Capacity:
                District_Capacity[District_Name] = float(FCI["Paddy_Procure"][i])
            else:
                District_Capacity[District_Name] += float(FCI["Paddy_Procure"][i])



        District_Demand = {}
        for i in range(len(FPS["Mill_District"])):
            District_Name_FPS = FPS["Mill_District"][i]
            if District_Name_FPS not in District_Demand:
                District_Demand[District_Name_FPS] = float(FPS["Milling_Capacity"][i])
            else:
                District_Demand[District_Name_FPS] += float(FPS["Milling_Capacity"][i])
                
        
        

                
        District_Name = [
            i for i in District_Capacity
            if District_Capacity[i] > District_Demand.get(i, 0)
        ]

        District_Name_1 = {}
        District_Name_1['District_Name_All'] = District_Name


        
        
        
        combined_data = {
            'District_Demand': District_Demand,
            'District_Capacity': District_Capacity,
            'District_Name': District_Name_1,
        }
        
        #print(combined_data)
        
        
        return jsonify(combined_data)
    except Exception as e:
        return jsonify({'status': 0, 'message': str(e)})
        
@app.route('/getGraphDataleg1', methods=['POST'])
def graph_dataleg1():
    try:
        usn = pd.ExcelFile('Backend//Data_2.xlsx')
        wh = pd.read_excel(usn, sheet_name='A.1 Warehouse', index_col=None)
        fci = pd.read_excel(usn, sheet_name='A.2 FCI', index_col=None)
        
        


        
        District_Capacity_Mota = {}
        for i in range(len(fci["PC_District"])):
            District_Name = fci["PC_District"][i]
            if District_Name not in District_Capacity_Mota:
                District_Capacity_Mota[District_Name] = float(fci["Wheat_Procure"][i])
            else:
                District_Capacity_Mota[District_Name] += float(fci["Wheat_Procure"][i])
                
                
                    
        

        District_Demand_Mota = {}
        for i in range(len(wh["SW_District"])):
            District_Name_FPS = wh["SW_District"][i]
            if District_Name_FPS not in District_Demand_Mota:
                District_Demand_Mota[District_Name_FPS] = float(wh["Available_Capacity"][i])
            else:
                District_Demand_Mota[District_Name_FPS] += float(wh["Available_Capacity"][i])
                
       
                
        
        
        District_Name = []
        District_Name = [
            i for i in District_Demand_Mota
            if i in District_Capacity_Mota and District_Capacity_Mota[i] > District_Demand_Mota[i]
        ]

        District_Name_1 = {}
        District_Name_1['District_Name_All'] = District_Name
        
        
        
        
        combined_data = {'District_Demand_Mota': District_Demand_Mota, 'District_Capacity_Mota': District_Capacity_Mota, 'District_Name': District_Name_1}
        
        
        
        
        
        return jsonify(combined_data)
    except Exception as e:
        return jsonify({'status': 0, 'message': str(e)})



def check_id_exists(connection, random_id):
    cursor = connection.cursor()
    query = "SELECT COUNT(*) FROM optimised_table WHERE id = %s"
    cursor.execute(query, (random_id,))
    result = cursor.fetchone()[0]
    return result > 0
    
def check_id_exists_leg1(connection, random_id):
    cursor = connection.cursor()
    query = "SELECT COUNT(*) FROM optimised_table_leg1 WHERE id = %s"
    cursor.execute(query, (random_id,))
    result = cursor.fetchone()[0]
    return result > 0   

def check_year_month_exists(connection, month, year, day):
    cursor = connection.cursor()
    query = "SELECT COUNT(*) FROM optimised_table WHERE month = %s and year = %s and day = %s"
    print(query)
    cursor.execute(query, (month,year,day))
    result = cursor.fetchone()[0]
    return result > 0
    
def check_year_month_exists_leg1(connection, month, year, day):
    cursor = connection.cursor()
    query = "SELECT COUNT(*) FROM optimised_table_leg1 WHERE month = %s and year = %s and day = %s"
    cursor.execute(query, (month,year,day))
    result = cursor.fetchone()[0]
    return result > 0

def get_year_month_exists(connection, month, year, day):
    cursor = connection.cursor()
    query = "SELECT id FROM optimised_table WHERE month = %s and year = %s and day = %s"
    cursor.execute(query, (month,year,day))
    result = cursor.fetchone()
    return result[0] if result else None
    
def get_year_month_exists_leg1(connection, month, year, day):
    cursor = connection.cursor()
    query = "SELECT id FROM optimised_table_leg1 WHERE month = %s and year = %s and day = %s"
    cursor.execute(query, (month,year,day))
    result = cursor.fetchone()
    return result[0] if result else None

#@app.route('/saveToDatabase', methods=['GET'])
def save_to_database(month, year, day):
    connection = connect_to_database()
    random_id = generate_random_id()
    while (check_id_exists(connection,random_id)):
        random_id = generate_random_id()
    table_name = "optimiseddata_" + str(random_id)
    pc_table = "pc_" + str(random_id)
    mill_table = "mill_" + str(random_id)
    if connection.is_connected():
        cursor = connection.cursor()
        current_datetime = datetime.now()
        formatted_datetime = current_datetime.strftime("%Y-%m-%d %H:%M:%S")
        if(check_year_month_exists(connection, month, year, day)):
            existingid = get_year_month_exists(connection, month, year, day);
            sql = "UPDATE optimised_table set last_updated='" + formatted_datetime + "' , cost = '""' WHERE id='" + existingid + "'"; 
            table_name = "optimiseddata_" + str(existingid)
            pc_table = "pc_" + str(existingid)
            mill_table = "mill_" + str(existingid)
            cursor.execute(sql)
        else:
            sql = "INSERT INTO optimised_table (id, month, year, day,last_updated) VALUES ('" + random_id + "','" + month + "','" + year + "','" + day + "','" + formatted_datetime + "')";
            cursor.execute(sql)
        connection.commit()
        pc_drop_query = 'DROP TABLE IF EXISTS ' + pc_table;
        cursor.execute(pc_drop_query)
        connection.commit()
        create_pc_query = ("CREATE TABLE " + pc_table + " (uniqueid VARCHAR(100) NOT NULL, PC_district VARCHAR(100) NOT NULL, PC_Name VARCHAR(100) NOT NULL,  PC_Lat VARCHAR(100) NOT NULL, PC_Long VARCHAR(100) NOT NULL, active VARCHAR(10) NOT NULL DEFAULT '1', PC_ID VARCHAR(100) NOT NULL, PC_Paddy VARCHAR(100) NOT NULL, Milling_Centre VARCHAR(100) NOT NULL, )")
        cursor.execute(create_pc_query)
        connection.commit()
        copy_pc_data = ("INSERT INTO " + pc_table + " SELECT * FROM pc WHERE active='1'")
        cursor.execute(copy_pc_data)
        connection.commit()
        
        mill_drop_query = 'DROP TABLE IF EXISTS ' + mill_table;
        cursor.execute(mill_drop_query)
        create_mill_query = ("CREATE TABLE " + mill_table + " (, district VARCHAR(100) NOT NULL, name VARCHAR(100) NOT NULL, id VARCHAR(100) NOT NULL,  latitude VARCHAR(100) NOT NULL, longitude VARCHAR(100) NOT NULL,milling_centre VARCHAR(100) NOT NULL,milling_capacity VARCHAR(100) NOT NULL, uniqueid VARCHAR(100) NOT NULL,active VARCHAR(10) NOT NULL DEFAULT '1')")
        cursor.execute(create_mill_query)
        connection.commit()
        copy_mill_data = ("INSERT INTO " + mill_table + " SELECT * FROM mill WHERE active='1'")
        cursor.execute(copy_mill_data)
        connection.commit()
        
        excel_file_path = 'Backend//Result_Sheet.xlsx'
        columns_to_fetch = ['Scenario','From','From_State','From_ID','From_Name','From_District','From_Milling_Center','From_Lat','From_Long','To','To_State','To_ID','To_Name', 'To_District','To_Milling_Center', 'To_Lat', 'To_Long','commodity','quantity','Distance']
        df = pd.read_excel(excel_file_path)
        selected_data = df[columns_to_fetch]
        sql = 'DROP TABLE IF EXISTS ' + table_name;
        cursor.execute(sql)
        connection.commit()
        
        sql = "CREATE TABLE " + table_name + " ( scenario VARCHAR(150) NOT NULL, `from` VARCHAR(150) NOT NULL,from_state VARCHAR(150) NOT NULL, from_id VARCHAR(150) NOT NULL, from_name VARCHAR(150) NOT NULL, from_district VARCHAR(150) NOT NULL,from_millingcentre VARCHAR(150) NOT NULL, from_lat VARCHAR(150) NOT NULL,from_long VARCHAR(150) NOT NULL, `to` VARCHAR(150) NOT NULL,to_state VARCHAR(150) NOT NULL,to_id VARCHAR(150) NOT NULL, to_name VARCHAR(150) NOT NULL, to_district VARCHAR(150) NOT NULL,to_millingcentre VARCHAR(150) NOT NULL, to_lat VARCHAR(150) NOT NULL, to_long VARCHAR(150) NOT NULL, commodity VARCHAR(150) NOT NULL,quantity VARCHAR(150) NOT NULL, distance VARCHAR(150) NOT NULL, approve_admin VARCHAR(100) , approve_district VARCHAR(100) , new_id_admin VARCHAR(100), new_id_district VARCHAR(100) , new_name_admin VARCHAR(100) , new_name_district VARCHAR(10) , reason_admin VARCHAR(255) , reason_district VARCHAR(255), new_distance_admin VARCHAR(100), new_distance_district VARCHAR(100), district_change_approve VARCHAR(100), status VARCHAR(100) )";
        cursor.execute(sql)
        connection.commit()
        
        for (index, row) in selected_data.iterrows():
            sql = 'INSERT INTO ' + table_name + ' (`scenario`, `from`, `from_state`, `from_id`, `from_name`, `from_district`,`from_millingcentre`, `from_lat`, `from_long`, `to`, `to_state`, `to_id`, `to_name`, `to_district`,`to_millingcentre`, `to_lat`, `to_long`, `commodity`, `quantity`, `distance`) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s)'
            values = tuple(row)
            cursor.execute(sql, values)
            connection.commit()
 
    if connection.is_connected():
        cursor.close()
        connection.close()
    return jsonify({'status': 1})
    
def save_to_database_leg1(month, year, day):
    connection = connect_to_database()
    random_id = generate_random_id()
    while (check_id_exists_leg1(connection,random_id)):
        random_id = generate_random_id()
    table_name = "optimiseddata_leg1_" + str(random_id)
    pc1_table = "pc1_leg1_" + str(random_id)
    warehouse_table = "warehouse_leg1_" + str(random_id)
    
    if connection.is_connected():
        cursor = connection.cursor()
        current_datetime = datetime.now()
        formatted_datetime = current_datetime.strftime("%Y-%m-%d %H:%M:%S")
        if(check_year_month_exists_leg1(connection, month, year, day)):
            existingid = get_year_month_exists_leg1(connection, month, year, day);
            sql = "UPDATE optimised_table_leg1 set last_updated='" + formatted_datetime + "' WHERE id='" + existingid + "'"; 
            #print(sql)
            table_name = "optimiseddata_leg1_" + str(existingid)
            pc1_table = "pc1_leg1_" + str(existingid)
            warehouse_table = "warehouse_leg1_" + str(existingid)
            
            cursor.execute(sql)
        else:
            sql = "INSERT INTO optimised_table_leg1 (id, month, year, day, last_updated) VALUES ('" + random_id + "','" + month + "','" + year + "','" + day + "','" + formatted_datetime + "')";
            cursor.execute(sql)
        
        connection.commit()
        pc1_drop_query = 'DROP TABLE IF EXISTS ' + pc1_table;
       
        cursor.execute(pc1_drop_query)
        connection.commit()
        create_pc1_query = ("CREATE TABLE " + pc1_table + " (uniqueid VARCHAR(100) NOT NULL, PC_District VARCHAR(100) NOT NULL, PC_Name VARCHAR(100) NOT NULL, PC_ID VARCHAR(100) NOT NULL, Agency_Name VARCHAR(100) NOT NULL, PC_Lat VARCHAR(100) NOT NULL, PC_Long VARCHAR(100) NOT NULL, PC_Paddy VARCHAR(100) NOT NULL, active VARCHAR(10) NOT NULL DEFAULT '1')")
        cursor.execute(create_pc1_query)
        connection.commit()
        copy_pc1_data = ("INSERT INTO " + pc1_table + " SELECT * FROM pc1 WHERE active='1'")
        cursor.execute(copy_pc1_data)
        connection.commit()
        
        warehouse_drop_query = 'DROP TABLE IF EXISTS ' + warehouse_table;
        cursor.execute(warehouse_drop_query)
        create_warehouse_query = ("CREATE TABLE " + warehouse_table + " (district VARCHAR(100) NOT NULL, name VARCHAR(100) NOT NULL, id VARCHAR(100) NOT NULL, agency_name VARCHAR(100) NOT NULL, warehousetype VARCHAR(100) NOT NULL, latitude VARCHAR(100) NOT NULL, longitude VARCHAR(100) NOT NULL,Warehouse_Capacity_Available VARCHAR(100) NOT NULL, uniqueid VARCHAR(100) NOT NULL, active VARCHAR(10) NOT NULL DEFAULT '1' )")
        cursor.execute(create_warehouse_query)
        connection.commit()
        copy_warehouse_data = ("INSERT INTO " + warehouse_table + " SELECT * FROM warehouse WHERE active='1'")
        cursor.execute(copy_warehouse_data)
        connection.commit()
        
        
        
        excel_file_path = 'Backend//Result_Sheet_leg1.xlsx'
        
        columns_to_fetch = ['Scenario','From','From_State','From_ID','From_Name','From_District','From_Lat','From_Long','To','To_State','To_ID','To_Name', 'To_District', 'To_Lat', 'To_Long','commodity','quantity','Distance']
        df = pd.read_excel(excel_file_path)
        selected_data = df[columns_to_fetch]
        sql = 'DROP TABLE IF EXISTS ' + table_name;
        cursor.execute(sql)
        connection.commit()
        
        sql = "CREATE TABLE " + table_name + " ( scenario VARCHAR(150) NOT NULL, `from` VARCHAR(150) NOT NULL,from_state VARCHAR(150) NOT NULL, from_id VARCHAR(150) NOT NULL, from_name VARCHAR(150) NOT NULL, from_district VARCHAR(150) NOT NULL, from_lat VARCHAR(150) NOT NULL,from_long VARCHAR(150) NOT NULL, `to` VARCHAR(150) NOT NULL,to_state VARCHAR(150) NOT NULL,to_id VARCHAR(150) NOT NULL, to_name VARCHAR(150) NOT NULL, to_district VARCHAR(150) NOT NULL, to_lat VARCHAR(150) NOT NULL, to_long VARCHAR(150) NOT NULL, commodity VARCHAR(150) NOT NULL,quantity VARCHAR(150) NOT NULL, distance VARCHAR(150) NOT NULL, approve_admin VARCHAR(100) , approve_district VARCHAR(100) , new_id_admin VARCHAR(100), new_id_district VARCHAR(100) , new_name_admin VARCHAR(100) , new_name_district VARCHAR(10) , reason_admin VARCHAR(255) , reason_district VARCHAR(255), new_distance_admin VARCHAR(100), new_distance_district VARCHAR(100), district_change_approve VARCHAR(100), status VARCHAR(100) )";
        cursor.execute(sql)
        connection.commit()
        
        for (index, row) in selected_data.iterrows():
            sql = 'INSERT INTO ' + table_name + ' (`scenario`, `from`, `from_state`, `from_id`, `from_name`, `from_district`, `from_lat`, `from_long`, `to`, `to_state`, `to_id`, `to_name`, `to_district`, `to_lat`, `to_long`, `commodity`, `quantity`, `distance`) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)'
            values = tuple(row)
            cursor.execute(sql, values)
            connection.commit()
 
    if connection.is_connected():
        cursor.close()
        connection.close()
    return jsonify({'status': 1})




#@app.route('/saveMonthlyData', methods=['POST'])
def save_monthly_data(month, year, day, data):
    connection = connect_to_database()
    table_name = "optimised_table"
    
    try:
        if connection.is_connected():
            cursor = connection.cursor()

            # Check if data for the given year and month already exists
            sql_check = "SELECT id FROM " + table_name + " WHERE year = %s AND month = %s AND day = %s"
            cursor.execute(sql_check, (year, month, day))
            existing_data = cursor.fetchone()

            if existing_data:
                # Update existing data
                
                sql_update = "UPDATE " + table_name + " SET data = %s WHERE id = %s"
                values_update = (data, existing_data[0])
                cursor.execute(sql_update, values_update)
            else:
                # Insert new data
                random_id = str(uuid.uuid4())
                sql_insert = "INSERT INTO " + table_name + " (month, year, day, data, id) VALUES (%s, %s, %s, %s, %s)"
                values_insert = (month, year, day, data, random_id)
                cursor.execute(sql_insert, values_insert)
            connection.commit()
    except mysql.connector.Error as err:
        # Handle the error, print or log it
        print(f"Error: {err}")
        return jsonify({'status': 0, 'error': str(err)})
    finally:
        if connection.is_connected():
            cursor.close()
            connection.close()
    
    return jsonify({'status': 1})


def save_monthly_data_leg1(month, year, day, data):
    connection = connect_to_database()
    table_name = "optimised_table_leg1"
    
    try:
        if connection.is_connected():
            cursor = connection.cursor()

            # Check if data for the given year and month already exists
            sql_check = "SELECT id FROM " + table_name + " WHERE year = %s AND month = %s AND day = %s"
            cursor.execute(sql_check, (year, month, day))
            existing_data = cursor.fetchone()

            if existing_data:
                # Update existing data
                sql_update = "UPDATE " + table_name + " SET data = %s WHERE id = %s"
                values_update = (data, existing_data[0])
                cursor.execute(sql_update, values_update)
            else:
                # Insert new data
                random_id = str(uuid.uuid4())
                sql_insert = "INSERT INTO " + table_name + " (month, year, day, data, id) VALUES (%s, %s, %s, %s, %s)"
                values_insert = (month, year, day, data, random_id)
                cursor.execute(sql_insert, values_insert)
            connection.commit()
    except mysql.connector.Error as err:
        # Handle the error, print or log it
        print(f"Error: {err}")
        return jsonify({'status': 0, 'error': str(err)})
    finally:
        if connection.is_connected():
            cursor.close()
            connection.close()
    
    return jsonify({'status': 1})


@app.route('/readMonthlyData', methods=['POST'])
def get_monthly_data():
    try:
        connection = connect_to_database()
        table_name = "optimised_table"

        if connection.is_connected():
            cursor = connection.cursor()

            # Retrieve all data from the monthlydata table
            sql_select_all = "SELECT year, month, data FROM " + table_name
            cursor.execute(sql_select_all)
            data_rows = cursor.fetchall()

            # Convert data to a list of dictionaries
            columns = [column[0] for column in cursor.description]
            result = [dict(zip(columns, row)) for row in data_rows]

    except mysql.connector.Error as err:
        # Handle the error, print or log it
        print(f"Error: {err}")
        return jsonify({'status': 0, 'error': str(err)})
    finally:
        if connection.is_connected():
            cursor.close()
            connection.close()

    return jsonify({'status': 1, 'data': result})
   
@app.route('/processCancel', methods=['POST'])
def processCancel():
    global stop_process
    stop_process = True
    data = {}
    data['status'] = 0
    data['message'] = "process stopped"
    json_data = json.dumps(data)
    json_object = json.loads(json_data)
    return json.dumps(json_object, indent=1)

@app.route('/processFile', methods=['POST'])
def processFile():
    global stop_process
    stop_process = False
    # CHANGE: if async=1, do not block this HTTP request;
    # spawn a background job and just return a job_id.
    if request.form.get("async") == "1":
        client_id = (
            request.form.get("client_id")
            or request.form.get("username")
            or request.form.get("user")
            or ""
        )
        if not client_id:
            # Still allow starting, but client won't be able to query via /active_job without a client_id.
            client_id = "anonymous"
        job_id = _job_create(client_id, endpoint="/processFile", message="queued")
        form_dict = request.form.to_dict(flat=True)
        # CHANGE: run heavy optimization in a separate OS process
        # so the Flask server thread stays responsive for polling.
        p = multiprocessing.Process(target=_run_processfile_in_background, args=(job_id, form_dict), daemon=True)
        p.start()
        return jsonify({"status": 1, "job_id": job_id, "message": "processing started"})
    # END CHANGE (async start mode)

    json_data = request.form
    write_log("User -> " + " Optimization Start for leg2 Requested JSON -> " + str(json_data))
    scenario_type = request.form.get('type')
    if scenario_type == "intra":
        message = 'DataFile file is incorrect'
        try:
            USN = pd.ExcelFile('Backend//Data_1.xlsx')
            month = request.form.get('month')        
            year = request.form.get('year')        
            day = request.form.get('day')
            print(day)
        except Exception as e:
            data = {}
            data['status'] = 0
            data['message'] = message
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        
    
    # ================= READ INPUT =================
    input_file = 'Backend//Data_1.xlsx'
    input1 = pd.ExcelFile(input_file)

    FCI = pd.read_excel(input1, sheet_name='A.1 Warehouse')
    FPS = pd.read_excel(input1, sheet_name='A.2 FPS')
    
   

    # ================= CHECK CONDITION =================

    # Calculate total paddy procurement demand
    total_demand = (FCI['Paddy_Procure'].sum())

    # Proceed only if demand exists
    if total_demand > 0:

        # ================= CLEAN DISTRICTS =================

        # Standardize milling centre names in FCI:
        # convert to string, remove spaces, and convert to lowercase
        FCI['Milling_Centre_P'] = (
            FCI['Milling_Centre_P']
            .astype(str)
            .str.replace(' ', '')
            .str.lower()
        )

        # Standardize milling centre names in FPS:
        # convert to string, remove spaces, and convert to lowercase
        FPS['Milling_Centre_M'] = (
            FPS['Milling_Centre_M']
            .astype(str)
            .str.replace(' ', '')
            .str.lower()
        )
        # ================= FIND COMMON DISTRICTS =================
        districts = list(set(FCI['Milling_Centre_P']).intersection(set(FPS['Milling_Centre_M'])))

        columns_18 = [
            'Scenario','From','From_State','From_District','From_ID','From_Name','From_Lat','From_Long',
            'To','To_ID','To_Name','To_State','To_District','To_Lat','To_Long','commodity','quantity'
        ]

        # =========================================================
        # ================= DISTRICT OPT ===========================
        # =========================================================
        if len(districts) > 0:

            final_df = pd.DataFrame()

            for dist_name in districts:
                 
                print(f"Running for district: {dist_name}")


                FCI_d = FCI[FCI['Milling_Centre_P'] == dist_name].reset_index(drop=True)
                FPS_d = FPS[FPS['Milling_Centre_M'] == dist_name].reset_index(drop=True)
                

                if len(FCI_d) == 0 or len(FPS_d) == 0:
                    continue

                # DISTANCE
                R = 6371
                dist = [[0]*len(FPS_d) for _ in range(len(FCI_d))]

                for i in FCI_d.index:
                    for j in FPS_d.index:
                        phi_1 = math.radians(FCI_d["WH_Lat"][i])
                        phi_2 = math.radians(FPS_d["FPS_Lat"][j])

                        dphi = math.radians(FPS_d["FPS_Lat"][j] - FCI_d["WH_Lat"][i])
                        dlambda = math.radians(FPS_d["FPS_Long"][j] - FCI_d["WH_Long"][i])

                        x = math.sin(dphi/2)**2 + math.cos(phi_1)*math.cos(phi_2)*math.sin(dlambda/2)**2
                        dist[i][j] = 2 * 6371 * math.atan2(math.sqrt(x), math.sqrt(1-x))

                # MODEL
                model = LpProblem(f'Supply_{dist_name}', LpMinimize)

                Allocation = LpVariable.matrix(
                    'X',
                    [(i,j) for i in range(len(FCI_d)) for j in range(len(FPS_d))],
                    lowBound=0
                )
                Allocation = np.array(Allocation).reshape(len(FCI_d), len(FPS_d))



                

                model += lpSum(Allocation[i][j] * dist[i][j]
                               for i in range(len(FCI_d))
                               for j in range(len(FPS_d)))
                               
                
                Supply = FCI_d["Paddy_Procure"].sum	
                Demand = FPS_d["Milling_Capacity"].sum       

                if     Supply >= Demand:                

                    for j in range(len(FPS_d)):
                        model += lpSum(Allocation[i][j] for i in range(len(FCI_d))) == FPS_d['Miling_Capacity'][j]

                    for i in range(len(FCI_d)):
                        model += lpSum(Allocation[i][j] for j in range(len(FPS_d))) <= FCI_d['Paddy_Procure'][i]
                        
                else: 
                    for j in range(len(FPS_d)):
                        model += lpSum(Allocation[i][j] for i in range(len(FCI_d))) <= FPS_d['Miling_Capacity'][j]

                    for i in range(len(FCI_d)):
                        model += lpSum(Allocation[i][j] for j in range(len(FPS_d))) == FCI_d['Paddy_Procure'][i]

                

                model.solve(PULP_CBC_CMD(msg=0, gapRel=0.3, timeLimit=600))
                
                

                status = LpStatus[model.status]
                if status not in ["Optimal", "Feasible"]:
                    print(f"Skipped {dist_name} - Status: {status}")
                    continue

                # ================= EXTRACT RESULT =================
                rows = []

                for i in range(len(FCI_d)):
                    for j in range(len(FPS_d)):
                        val = Allocation[i][j].value()
                        if val and val > 0:
                            rows.append({
                                
                                'PC_ID': FCI_d['WH_ID'][i],
                                'WH_D': dist_name,
                                'Mill_ID': FPS_d['FPS_ID'][j],
                                'FPS_D': dist_name,
                                'Values': val
                            })

                df_temp = pd.DataFrame(rows)

                final_df = pd.concat([final_df, df_temp], ignore_index=True)


            # ================= SAVE OUTPUT =================
            final_df.to_excel('Backend//Tagging_Sheet_Pre.xlsx', index=False)

            print("✅ LP District-wise tagging completed") 
            
            
                    

            
            
                

            df31 = pd.read_excel('Backend//Tagging_Sheet_Pre.xlsx')
            USN = pd.ExcelFile('Backend//Data_1.xlsx')
            FCI = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
            FPS = pd.read_excel(USN, sheet_name='A.2 FPS', index_col=None)
            
            df4 = pd.merge(df31, FCI, on='PC_ID', how='inner')
            df4 = df4[[
                'PC_ID',
                'PC_Name',
                'PC_District',
                'Milling_Centre_P',
                'PC_Lat',
                'PC_Long',
                'Mill_ID',
                'Values',
                ]]
                
            df4 = pd.merge(df4, FPS, on='Mill_ID', how='inner')
            
            df51 = df4[[
                'PC_ID',
                'PC_Name',
                'PC_District',
                'Milling_Centre_P',
                'PC_Lat',
                'PC_Long',
                'Mill_ID',
                'Mill_Name',
                'Mill_District',
                'Milling_Centre_M',
                'Mill_Lat',
                'Mill_Long',
                'Values',
            ]]
            
            df51.insert(0, 'Scenario', 'Optimized')
            df51.insert(1, 'From', 'PC')
            df51.insert(2, 'From_State', 'Punjab')
            df51.insert(7, 'To', 'Mill')
            df51.insert(8, 'To_State', 'Punjab')
            df51.insert(9, 'commodity', 'Paddy')
            
            df51.rename(columns={
                'PC_ID': 'From_ID',
                'PC_Name': 'From_Name',
                'PC_Lat': 'From_Lat',
                'PC_Long': 'From_Long',
                'Milling_Centre_P': 'From_Milling_Center',
            }, inplace=True)
            
            df51.rename(columns={
                'Mill_ID': 'To_ID',
                'Mill_Name': 'To_Name',
                'Mill_Lat': 'To_Lat',
                'Mill_Long': 'To_Long',
                'Milling_Centre_M': 'To_Milling_Center',
                'Values': 'quantity',
            }, inplace=True)
            
            df51.rename(columns={'PC_District': 'From_District',
            'Mill_District': 'To_District'}, inplace=True)
            df51 = df51.loc[:, [
                'Scenario',
                'From',
                'From_State',
                'From_District',
                'From_Milling_Center',
                'From_ID',
                'From_Name',
                'From_Lat',
                'From_Long',
                'To',
                'To_ID',
                'To_Name',
                'To_State',
                'To_District',
                'To_Milling_Center',
                'To_Lat',
                'To_Long',
                'commodity',
                'quantity',
                ]]
            
            
            
            
            def convert_to_numeric(value):
                try:
                    return pd.to_numeric(value)
                except ValueError:
                    return value
                    
            
            df51['From_ID'] = df51['From_ID'].apply(convert_to_numeric)
            df51['To_ID'] = df51['To_ID'].apply(convert_to_numeric)
            
            df_combined = pd.concat([df51])
            df_combined1 = df_combined[df_combined['quantity'] != 0]
            df_combined1['From_ID'] = df_combined1['From_ID'].apply(convert_to_numeric)
            df_combined1['To_ID'] = df_combined1['To_ID'].apply(convert_to_numeric)
                    
           
            df_combined1.to_excel('Backend//Tagging_Sheet_Pre11.xlsx', sheet_name='BG_FPS',index=False,)


        else:
            pd.DataFrame(columns=columns_18).to_excel('Backend//Tagging_Sheet_Pre11.xlsx', index=False)
                
        
        
        
        df51.to_excel('Backend//Tagging_Sheet_Pre11.xlsx', sheet_name='BG_FPS1')
        data1 = pd.ExcelFile("Backend//Tagging_Sheet_Pre11.xlsx")
        df5 = pd.read_excel(data1,sheet_name="BG_FPS1")
        data1.close()
        
        
        
        
             
        
        
        
        input = pd.ExcelFile('Backend/Data_1.xlsx')
        node1 = pd.read_excel(input,sheet_name="A.1 Warehouse")
        node1["concatenate"]= node1['WH_Lat'].round(3).astype(str) + ',' + node1['WH_Long'].round(3).astype(str)
        
        node2 = pd.read_excel(input,sheet_name="A.2 FPS")
        node2["concatenate1"]= node2['FPS_Lat'].round(3).astype(str) + ',' + node2['FPS_Long'].round(3).astype(str)
        
        DistanceBing = read_protected_excel('Backend//Distance_Initial_L2.xlsx', 'distf', sheet_name='BG_BG')
        Warehouse = read_protected_excel('Backend//Distance_Initial_L2.xlsx', 'distf', sheet_name='Warehouse')
        FPS = read_protected_excel('Backend//Distance_Initial_L2.xlsx', 'distf', sheet_name='FPS')
       
        
        Warehouse['Lat_Long_r'] = (
            Warehouse['Lat_Long']
            .str.split(',', expand=True)
            .apply(pd.to_numeric, errors='coerce')
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )
        
        FPS['Lat_Long_r'] = (
            FPS['Lat_Long']
            .str.split(',', expand=True)
            .apply(pd.to_numeric, errors='coerce')
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        
        node1 = node1[['WH_ID', 'WH_Lat', 'WH_Long','concatenate']]
        War = pd.merge(node1, Warehouse, on='WH_ID')
        df1_w = War[War['concatenate'] != War['Lat_Long_r']]
        Warehouse_ID = df1_w['WH_ID'].unique()
        
        
        
        node2 = node2[['FPS_ID', 'FPS_Lat', 'FPS_Long','concatenate1']]
        FPS1 = pd.merge(node2, FPS, on='FPS_ID')
        df1_f = FPS1[FPS1['concatenate1'] != FPS1['Lat_Long_r']]
        FPS_ID = df1_f['FPS_ID'].unique()


        BG_BG = DistanceBing
        Distance1 = BG_BG.drop(columns=BG_BG.columns[BG_BG.columns.isin(Warehouse_ID)])
        Distance2 =Distance1.T
        Distance3 = Distance2.drop(columns=Distance2.columns[Distance2.columns.isin(FPS_ID)])
        Distance3 = Distance3.T
        with pd.ExcelWriter('Backend//Punjab_Distance_L2.xlsx') as writer:
            Distance3.to_excel(writer, sheet_name='BG_BG',index=False)
            

        Cost = pd.ExcelFile("Backend//Punjab_Distance_L2.xlsx")
        BG_BG = pd.read_excel(Cost,sheet_name="BG_BG")
        Cost.close()
        data1 = pd.ExcelFile("Backend//Tagging_Sheet_Pre11.xlsx")
        df5 = pd.read_excel(data1,sheet_name="BG_FPS1")
        data1.close()

        Distance_BG_BG = {}
        column_list_BG_BG = list(BG_BG.columns.astype(str))
        row_list_BG_BG = list(BG_BG.iloc[:, 0].astype(str))

        for ind in df5.index:
            from_code = df5['From_ID'][ind]
            to_code = df5['To_ID'][ind]
            from_code_str = str(from_code)
            to_code_str = str(to_code)
            
            if to_code_str in row_list_BG_BG and from_code_str in column_list_BG_BG:
                index_i = row_list_BG_BG.index(to_code_str)
                index_j = column_list_BG_BG.index(from_code_str)
                key = to_code_str + "_" + from_code_str
                Distance_BG_BG[key] = BG_BG.iloc[index_i, index_j] 
                
                
        df5["Tagging"] = df5['To_ID'].astype(str) + '_' + df5['From_ID'].astype(str)
        df5['Distance'] = df5['Tagging'].map(Distance_BG_BG)
        df5.fillna('shallu', inplace=True)
        df5.to_excel('Backend//Result_Sheet12.xlsx', sheet_name='Warehouse_FPS', index=False)        
        
        
        
        
        
# ----------------------------------------------------------------------------------------------------------------------------------------------
        Result_Sheet1 = pd.ExcelFile("Backend//Result_Sheet12.xlsx")
        df6 = pd.read_excel(Result_Sheet1, sheet_name="Warehouse_FPS")

        # Filter rows where Distance == 'shallu'
        df7 = df6.loc[df6['Distance'] == "shallu"]

        auth_url = 'https://kerala.pmgatishakti.gov.in/PMGatishaktiApiService/authenticate'
        distance_url = 'https://kerala.pmgatishakti.gov.in/PMGatishaktiApiService/dfpdapi/roaddistance'

        auth_payload = {
            "username": "DFPD_C",
            "password": "W9Vtb8WKkt3"
        }

        FILE_PATH = 'distanceIndent.json'

        # 10 minutes

        def get_token():
            """Authenticate and return cached Gatishakti token (refreshes after 10 minutes)."""
            global auth_token, token_timestamp
            current_time = time.time()

            # ✅ Reuse existing valid token
            if auth_token and (current_time - token_timestamp) < TOKEN_VALIDITY_SECONDS:
                return auth_token

            # 🔄 Generate a new one if expired or missing
            try:
                response = requests.post(auth_url, json=auth_payload, timeout=20)
                if response.status_code == 200:
                    token = response.json().get('token')
                    if token:
                        auth_token = token
                        token_timestamp = current_time
                        print("🔐 Token generated successfully.")
                        return token
                    else:
                        print(" Token missing in response.")
                else:
                    print(f"Failed to get token: {response.status_code}")
            except Exception as e:
                print(f"Error getting token: {e}")

            return False

        def process_batch(df_batch):
            """Send multiple rows in one Gatishakti request."""
            token = get_token()
            if not token:
                print("⚠️ No token received — batch skipped.")
                return None

            time.sleep(5)  # avoid rate limit
            headers = {'Authorization': f'Bearer {token}'}

            data = {
                "parameter": [{
                    "src_lng": row["From_Long"],
                    "src_lat": row["From_Lat"],
                    "dest_lng": row["To_Long"],
                    "dest_lat": row["To_Lat"]
                } for _, row in df_batch.iterrows()]
            }

            with open(FILE_PATH, 'w') as f:
                json.dump(data, f, indent=4)

            with open(FILE_PATH, 'rb') as f:
                files = {'LatsLongsFile': f}
                try:
                    response = requests.post(distance_url, headers=headers, files=files, timeout=60)
                    return response
                except Exception as e:
                    print(f" Batch request failed: {e}")
                    return None

        def process_single(row):
            """Fetch distance for a single pair using Gatishakti. Returns distance or 0."""
            token = get_token()
            if not token:
                print(f" No token for From_ID={row['From_ID']} → distance set to 0")
                return 0

            time.sleep(2)
            headers = {'Authorization': f'Bearer {token}'}

            data = {
                "parameter": [{
                    "src_lng": row["From_Long"],
                    "src_lat": row["From_Lat"],
                    "dest_lng": row["To_Long"],
                    "dest_lat": row["To_Lat"]
                }]
            }

            with open(FILE_PATH, 'w') as f:
                json.dump(data, f, indent=4)

            with open(FILE_PATH, 'rb') as f:
                files = {'LatsLongsFile': f}
                try:
                    response = requests.post(distance_url, headers=headers, files=files, timeout=30)
                    if response.status_code == 200:
                        single_json = response.json()
                        if (
                            'data' in single_json and 
                            len(single_json['data']) > 0 and 
                            'distance' in single_json['data'][0]
                        ):
                            dist = single_json['data'][0]['distance']
                            print(f" From_ID={row['From_ID']} Distance={dist}")
                            return dist
                except Exception as e:
                    print(f" Error in single request ({row['From_ID']}): {e}")

            print(f"⚠️ No distance for From_ID={row['From_ID']} → set 0")
            return 0

        batch_size = 80
        total_rows = len(df7)
        num_batches = (total_rows + batch_size - 1) // batch_size

        dist3 = []

        for batch_num in range(num_batches):
            start_idx = batch_num * batch_size
            end_idx = min((batch_num + 1) * batch_size, total_rows)
            df_batch = df7.iloc[start_idx:end_idx]

            print(f"\n🚀 Processing batch {batch_num + 1}/{num_batches} (rows {start_idx + 1}-{end_idx})")

            response = process_batch(df_batch)

            if response and response.status_code == 200:
                response_json = response.json()

                #  If batch Gatishakti call works fine
                if 'data' in response_json and all('distance' in row_data for row_data in response_json['data']):
                    for row_data, (_, row) in zip(response_json['data'], df_batch.iterrows()):
                        distance = row_data['distance']
                        dist3.append(distance)
                        print(f"Batch distance for From_ID={row['From_ID']} → {distance}")
                else:
                    # Fallback to single calls if any missing
                    print("⚠️ Batch incomplete — retrying missing rows one by one...")
                    for i, (_, row) in enumerate(df_batch.iterrows()):
                        if (
                            'data' in response_json and 
                            i < len(response_json['data']) and 
                            'distance' in response_json['data'][i]
                        ):
                            dist = response_json['data'][i]['distance']
                            dist3.append(dist)
                        else:
                            distance = process_single(row)
                            dist3.append(distance if distance else 0)
            else:
                #  Batch failed completely
                print("Batch API failed — retrying all rows individually...")
                for _, row in df_batch.iterrows():
                    distance = process_single(row)
                    dist3.append(distance if distance else 0)


        df7["Distance"] = dist3

        # Merge with old data
        df9 = df6.loc[df6['Distance'] != "shallu"]
        df10 = pd.concat([df9, df7], ignore_index=True)

        # Compute total result
        df10["Distance"] = df10["Distance"].fillna(0)
        df10["quantity"] = df10["quantity"].fillna(0)
        result = ((df10['quantity']) * df10['Distance']).sum()

        

        # Save into one Excel file
        df10.to_excel('Backend//Result_Sheet.xlsx', sheet_name='Warehouse_FPS', index=False)
                
# ----------------------------------------------------------------------------------------------------------------------------------------------
        data = {}
        
        dfinal = pd.read_excel('Backend//Result_Sheet.xlsx', sheet_name='Warehouse_FPS')
        
        data["Scenario"]="Intra"
        data["Scenario_Baseline"] = "Baseline"
        
        data["WH_Used"] = dfinal['From_ID'].nunique()
        data["WH_Used_Baseline"] = "198"
        
        data["FPS_Used"] = dfinal['To_ID'].nunique()
        data["FPS_Used_Baseline"] = "13,649"
        
        
        
        data['Demand'] = pd.to_numeric(dfinal["quantity"], errors='coerce').fillna(0).sum()
        data['Demand_Baseline'] = "23,62,728"
        result1 = ((dfinal['quantity']) * dfinal['Distance']).sum()
        data['Total_QKM'] = float(result1)

        data['Total_QKM_Baseline'] = "4,99,58,425"
        
        Total_Demand=pd.to_numeric(dfinal["quantity"], errors='coerce').fillna(0).sum()
        
        data['Average_Distance'] = float(round(result1, 2)) / Total_Demand
        data['Average_Distance_Baseline'] = "21.14"

        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)                     

        save_to_database(month, year, day)
        save_monthly_data(month, year, day, float(result))
        
        def delete_files(file_paths):
            deleted_files = []
            failed_files = []
            
            for file_path in file_paths:
                try:
                    if os.path.exists(file_path):  # Check if the file exists
                        os.remove(file_path)  # Delete the file
                        deleted_files.append(file_path)  # Track successfully deleted files
                    else:
                        failed_files.append((file_path, "File does not exist"))
                except Exception as e:
                    failed_files.append((file_path, str(e)))  # Track failed deletions with error message
                    
            return deleted_files, failed_files
            
        # List of files to delete
        files_to_delete = [
            'Backend/Chattisgarh_Distance_L2.xlsx',
            'Backend/Result_Sheet12.xlsx',
            'Backend/Result_Sheet12',
            'Backend//Tagging_Sheet_Pre11.xlsx',
            
        ]

        # Call the function to delete the files
        delete_files(files_to_delete)

        json_data = json.dumps(data)
        json_object = json.loads(json_data)

        if os.path.exists('ouputPickle.pkl'):
            os.remove('ouputPickle.pkl')

        # open pickle file
        dbfile1 = open('ouputPickle.pkl', 'ab')
        
    else:
        message = 'DataFile file is incorrect'
        try:
            USN = pd.ExcelFile('Backend//Data_1.xlsx')
            month = request.form.get('month')        
            year = request.form.get('year')        
            day = request.form.get('day')
            print(day)
        except Exception as e:
            data = {}
            data['status'] = 0
            data['message'] = message
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        input = pd.ExcelFile('Backend//Data_1.xlsx')
        node1 = pd.read_excel(input,sheet_name="A.1 Warehouse")
        node2 = pd.read_excel(input,sheet_name="A.2 FPS")

        node1 = pd.read_excel(input,sheet_name="A.1 Warehouse")
        node2 = pd.read_excel(input,sheet_name="A.2 FPS")
        dist = [[0 for a in range(len(node2["FPS_ID"]))] for b in range(len(node1["WH_ID"]))]
        phi_1 = []
        phi_2 = []
        delta_phi = []
        delta_lambda = []
        R = 6371 

        for i in node1.index:
            for j in node2.index:
                phi_1=math.radians(node1["WH_Lat"][i])
                phi_2=math.radians(node2["FPS_Lat"][j])
                delta_phi=math.radians(node2["FPS_Lat"][j]-node1["WH_Lat"][i])
                delta_lambda=math.radians(node2["FPS_Long"][j]-node1["WH_Long"][i])
                x=math.sin(delta_phi / 2.0) ** 2 + math.cos(phi_1) * math.cos(phi_2) * math.sin(delta_lambda / 2.0) ** 2
                y=2 * math.atan2(math.sqrt(x), math.sqrt(1 - x))
                dist[i][j]=R*y
                
       
        FCI = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
        FPS = pd.read_excel(USN, sheet_name='A.2 FPS', index_col=None)
        # print(FCI.columns)
        FCI['WH_District'] = FCI['WH_District'].apply(lambda x: x.replace(' ', ''))
        FPS['FPS_District'] = FPS['FPS_District'].apply(lambda x: x.replace(' ', ''))
        

        # Warehouse_No = []
        # FPS_No = []
        # Warehouse_No = FCI['WH_ID'].nunique()
        # FPS_No = FPS['FPS_ID'].nunique()
        # Warehouse_Count = {}

        # FPS_Count = {}
        # Warehouse_Count['Warehouse_Count'] = Warehouse_No
        # FPS_Count['FPS_Count'] = FPS_No  # No of FPS

        # Total_Supply = []
        # Total_Supply_Warehouse = {}
        # # Total_Supply = FCI['Storage_Capacity'].sum()
        # Total_Supply_Warehouse['Total_Supply_Warehouse'] = Total_Supply  # Total SUPPLY

        # Total_Demand = []
        # Total_Demand_FPS = {}
        # # Total_Demand = FPS['Allocation_Wheat'].sum()
        # # Total_Demand_FPS['Total_Demand_Warehouse'] = Total_Demand  # Total demand

        # FCI_district = []
        # FCI_Data = {}
        # Disrticts_FCI = {}
        # if stop_process==True:
        #     data = {}
        #     data['status'] = 0
        #     data['message'] = "Process Stopped"
        #     json_data = json.dumps(data)
        #     json_object = json.loads(json_data)
        #     return json.dumps(json_object, indent=1)
        # for (i, j) in zip(FCI['WH_District'], FCI['WH_ID']):
        #     i = i.lower()
        #     if i not in FCI_district:
        #         FCI_district.append(i)
        #         globals()['FCI_' + str(i)] = []
        #     globals()['FCI_' + str(i)].append(j)
        # for i in FCI_district:
        #     FCI_Data[i] = globals()['FCI_' + str(i)]
        # Disrticts_FCI['Disrticts_FCI'] = FCI_district
        # if stop_process==True:
        #     data = {}
        #     data['status'] = 0
        #     data['message'] = "Process Stopped"
        #     json_data = json.dumps(data)
        #     json_object = json.loads(json_data)
        #     return json.dumps(json_object, indent=1)

        # District_Capacity = {}
        # for i in range(len(FCI['WH_District'])):
        #     District_Name = FCI['WH_District'][i]
        #     if District_Name not in District_Capacity:
        #         District_Capacity[District_Name] = FCI['Storage_Capacity'][i]
        #     else:
        #         District_Capacity[District_Name] = FCI['Storage_Capacity'][i] + District_Capacity[District_Name]

        # FPS_district = []
        # FPS_Data = {}
        # Districts_FPS = {}
        # for (i, j) in zip(FPS['FPS_District'], FPS['FPS_Tehsil']):
        #     i = i.lower()
        #     if i not in FPS_district:
        #         FPS_district.append(i)
        #         globals()['FPS_' + str(i)] = []
        #     if j not in globals()['FPS_' + str(i)]:
        #         globals()['FPS_' + str(i)].append(j)
        # for i in FPS_district:
        #     FPS_Data[i] = globals()['FPS_' + str(i)]
        #     Districts_FPS['Districts_FPS'] = FPS_district

        # District_Demand = {}
        # for i in range(len(FPS['FPS_District'])):
        #     District_Name_FPS = FPS['FPS_District'][i]
        #     if District_Name_FPS not in District_Demand:
        #         District_Demand[District_Name_FPS] = FPS['Allocation_Wheat'][i]
        #     else:
        #         District_Demand[District_Name_FPS] = FPS['Allocation_Wheat'][i] + District_Demand[District_Name_FPS]
        # if stop_process==True:
        #     data = {}
        #     data['status'] = 0
        #     data['message'] = "Process Stopped"
        #     json_data = json.dumps(data)
        #     json_object = json.loads(json_data)
        #     return json.dumps(json_object, indent=1)

        # FCI_district = []
        # FCI_Data = {}
        # Disrticts_FCI = {}
        # Data_state_wise = {}
        # Data_statewise = {}

        # for (i, j) in zip(FCI['WH_District'], FCI['WH_ID']):
        #     i = i.lower()
        #     if i not in FCI_district:
        #         FCI_district.append(i)
        #         globals()['FCI_' + str(i)] = []
        #     globals()['FCI_' + str(i)].append(j)
        # for i in FCI_district:
        #     FCI_Data[i] = globals()['FCI_' + str(i)]
        # Disrticts_FCI['Disrticts_FCI'] = FCI_district
        
        # if stop_process==True:
        #     data = {}
        #     data['status'] = 0
        #     data['message'] = "Process Stopped"
        #     json_data = json.dumps(data)
        #     json_object = json.loads(json_data)
        #     return json.dumps(json_object, indent=1)
        
        # FPS_district = []
        # FPS_Data = {}
        # Districts_FPS = {}
        # for (i, j) in zip(FPS['FPS_District'], FPS['FPS_Tehsil']):
        #     i = i.lower()
        #     if i not in FPS_district:
        #         FPS_district.append(i)
        #         globals()['FPS_' + str(i)] = []
        #     if j not in globals()['FPS_' + str(i)]:
        #         globals()['FPS_' + str(i)].append(j)
        # for i in FPS_district:
        #     FPS_Data[i] = globals()['FPS_' + str(i)]
        # Districts_FPS['Districts_FPS'] = FPS_district

        model = LpProblem('Supply-Demand-Problem', LpMinimize)

        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        Variable1 = []
        
        for i in range(len(FCI['WH_ID'])):
            for j in range(len(FPS['FPS_ID'])):
                Variable1.append(str(FCI['WH_ID'][i]) + '_'
                                 + str(FCI['WH_District'][i]) + '_'
                                 + str(FPS['FPS_ID'][j]) + '_'
                                 + str(FPS['FPS_District'][j]) + '_Wheat')
                                 
        

        # Variables for Wheat from lEVEL2 TO FPS

        DV_Variables1 = LpVariable.matrix('X', Variable1, cat='float',
                lowBound=0)
        Allocation1 = np.array(DV_Variables1).reshape(len(FCI['WH_ID']),
                len(FPS['FPS_ID']))
                
             
                
                

        Variable1I = []
        Allocation1I = []
        for i in range(len(FCI['WH_ID'])):
            for j in range(len(FPS['FPS_ID'])):
                Variable1I.append(str(FCI['WH_ID'][i]) + '_'
                                  + str(FCI['WH_District'][i]) + '_'
                                  + str(FPS['FPS_ID'][j]) + '_'
                                  + str(FPS['FPS_District'][j]) + '_Wheat1')

    #    Variables for Wheat from IG TO FPS

        DV_Variables1I = LpVariable.matrix('X', Variable1I, cat='Binary',lowBound=0)
        Allocation1I = np.array(DV_Variables1I).reshape(len(FCI['WH_ID']),len(FPS['FPS_ID']))

        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        for i in range(len(FPS['FPS_ID'])):
             model += lpSum(Allocation1I[k][i] for k in range(len(FCI['WH_ID']))) <= 1

        for i in range(len(FCI['WH_ID'])):
             for j in range(len(FPS['FPS_ID'])):
                model += Allocation1[i][j] <= 1000000 * Allocation1I[i][j]
                
        
        
        District_Capacity = {}
        for i in range(len(FCI["WH_District"])):
            District_Name = FCI["WH_District"][i]
            if District_Name not in District_Capacity:
                District_Capacity[District_Name] = int(FCI["Storage_Capacity"][i])
            else:
                District_Capacity[District_Name] += int(FCI["Storage_Capacity"][i])
 
        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        District_Demand = {}
        for i in range(len(FPS["FPS_District"])):
            District_Name_FPS = FPS["FPS_District"][i]
            if District_Name_FPS not in District_Demand:
                District_Demand[District_Name_FPS] = float(FPS["Allocation_Wheat"][i]) + float(FPS["Allocation_Rice"][i])
            else:
                District_Demand[District_Name_FPS] += float(FPS["Allocation_Wheat"][i]) + float(FPS["Allocation_Rice"][i])
                
        

        
        District_Name = []
        District_Name2=[]
        District_Name = [i for i in District_Demand if i not in District_Capacity]
        District_Name4 = [i for i in District_Capacity if i not in District_Demand]
        District_Name2 = [i for i in District_Demand if i in District_Capacity and District_Demand[i] >= District_Capacity[i]]
        District_Name_1 = {}
        District_Name_1['District_Name_All'] = District_Name + District_Name2
        District_Name3 = [i for i in District_Demand if i in District_Capacity and District_Demand[i] <= District_Capacity[i]]
        
        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)        
        
        Tehsil = {}
        UniqueId = 0
        Tehsil_temp = []
        Tehsil_rev = {}

        for i in FPS['FPS_Tehsil']:
            Tehsil_temp.append(i)
            if i not in Tehsil:
                Tehsil[i] = UniqueId
                Tehsil_rev[UniqueId] = i
                UniqueId = UniqueId + 1

        Tehsil_FPS = []
        for i in range(len(FPS['FPS_ID'])):
            Tehsil_FPS.append(Tehsil[Tehsil_temp[i]])

        

        allCombination1 = []
        

        for i in range(len(dist)):
            for j in range(len(FPS['FPS_ID'])):
                allCombination1.append(Allocation1[i][j] *dist[i][j])
        
        

        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        model += lpSum(allCombination1)

        # Demand Constraints for Wheat
        
        FPS["Demand"]=FPS["Allocation_Wheat"]+ FPS["Allocation_Rice"]

        for i in range(len(FPS['FPS_ID'])):
            model += lpSum(Allocation1[j][i] for j in range(len(FCI['WH_ID'
                           ]))) >= FPS['Demand'][i]
                           
       

        # Supply Constraints for Warehouses

        for i in range(len(FCI['WH_ID'])):
            model += (lpSum(Allocation1[i][j] for j in range(len(FPS['FPS_ID'
                           ])))  <= FCI['Storage_Capacity'][i])

       # Calling CBC_CMB Solver

        #model.solve(CPLEX_CMD(options=['set mip tolerances mipgap 0.01']))
        model.prob.solve(CPLEX_CMD(options=["set mip tolerances mipgap 0.01","set emphasis memory y","set mip strategy file 3","set workmem 2048"]))
        #model.solve(CPLEX_CMD(options=['set mip tolerances mipgap 0.03',"set emphasis memory y"]))
        #model.solve(PULP_CBC_CMD())
        
        status = LpStatus[model.status]
        if status == "Infeasible" or status == "Unbounded" or status == "NotSolved" or status == "Undefined":
           print("Problem is infeasible or unbounded.")
           data = {}
           data['status'] = 0
           data['message'] = "Infeasible or Unbounded Solution"
           json_data = json.dumps(data)
           json_object = json.loads(json_data)
           return json.dumps(json_object, indent=1)
 
        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        #model.solve(PULP_CBC_CMD())
        
        
        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        Original_Cost = 100000000
        total = Original_Cost

        data = {}
        #data['status'] = 1
        #data['modelStatus'] = Status
        #data['totalCost'] = float(round(model.objective.value(),1))
        #data['original'] = float(round(total, 2))
        #data['percentageReduction'] = float(round((total
                #- model.objective.value()) / total, 4) * 100)
        #data['Average_Distance'] = float(round(model.objective.value(), 2)) / Total_Demand
        #data['Demand'] = int(FPS['Allocation_Wheat'].sum())

        BGW = {}
        BGR = {}
        IGW = {}
        IGR = {}
        FCIW = {}

        BGCapacity = {}

        temp = {}
        for i in range(len(FCI['WH_ID'])):
            temp[str(FCI['WH_ID'][i])] = str(FCI['Storage_Capacity'])
        BGCapacity = temp

        temp1 = {}
        BG_FPS = [[] for i in range(len(Tehsil))]
        for i in range(len(FCI['WH_ID'])):
            for j in range(len(FPS['FPS_ID'])):
                BG_FPS[Tehsil_FPS[j]].append(Allocation1[i][j].value())
            temp1[str(FCI['WH_ID'][i])] = \
                str(lpSum(Allocation1[i][j].value() for j in
                    range(len(FPS['FPS_ID']))))
            BGCapacity[str(FCI['WH_ID'][i])] = str(FCI['Storage_Capacity'
                    ][i])
        BGW['FPS'] = temp1

        BG_FPS_Wheat = {}
        for i in range(len(Tehsil)):
            BG_FPS_Wheat[str(Tehsil_rev[i])] = str(lpSum(BG_FPS[i]))

        BG_FPS_Rice = {}
        for i in range(len(Tehsil)):
            BG_FPS_Rice[str(Tehsil_rev[i])] = str(lpSum(BG_FPS[i]))

        data['BGW'] = BGW
        data['BGR'] = BGR
        data['FPSW'] = BG_FPS_Wheat
        data['FPSR'] = BG_FPS_Rice
        data['BGCapacity'] = BGCapacity

        wheat_total_dict = data['BGW']['FPS']

        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        wheat_total = 0
        for value in wheat_total_dict:
            if float(wheat_total_dict[value]):
                wheat_total = int(wheat_total + float(wheat_total_dict[value]))

        total_commodity = int(wheat_total)

        Output_File = open('Backend//Inter_District1.csv', 'w')
        for v in model.variables():
            if v.value() > 0:
                Output_File.write(v.name + '\t' + str(v.value()) + '\n')

        Output_File = open('Backend//Inter_District1.csv', 'w')
        for v in model.variables():
            if v.value() > 0:
                Output_File.write(v.name + '\t' + str(v.value()) + '\n')


        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        df9 = pd.read_csv('Backend//Inter_District1.csv',header=None)
        df9.columns = ['Tagging']
        df9[[
            'Var',
            'WH_ID',
            'W_D',
            'FPS_ID',
            'FPS_D',
            'commodity_Value',
            ]] = df9[df9.columns[0]].str.split('_', n=6, expand=True)
        del df9[df9.columns[0]]
        df9[['commodity', 'Values']] = df9['commodity_Value'
                ].str.split('\\t', n=1, expand=True)
        del df9['commodity_Value']
        df9 = df9.drop(np.where(df9['commodity'] == 'Wheat1')[0])
        
        def convert_to_numeric(value):
            try:
                return pd.to_numeric(value)
            except ValueError:
                return value
        
        
        df9['WH_ID'] = df9['WH_ID'].apply(convert_to_numeric)
        df9['FPS_ID'] = df9['FPS_ID'].apply(convert_to_numeric)
        
        df9.to_excel('Backend//Tagging_Sheet_Pre.xlsx', sheet_name='BG_FPS')
        df31 = pd.read_excel('Backend//Tagging_Sheet_Pre.xlsx')
        
        USN = pd.ExcelFile('Backend//Data_1.xlsx')
        FCI = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
        FPS = pd.read_excel(USN, sheet_name='A.2 FPS', index_col=None)
       


        df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        #df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        df4 = df4[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'FPS_ID',
            'Values',
            ]]
        df4 = pd.merge(df4, FPS, on='FPS_ID', how='inner')
        df51 = df4[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'FPS_ID',
            'FPS_Name',
            'FPS_District',
            'FPS_Lat',
            'FPS_Long',
            'Allocation_Wheat',
            ]]
        df51.insert(0, 'Scenario', 'Optimized')
        df51.insert(1, 'From', 'Depot')
        df51.insert(2, 'From_State', 'Chhattisgarh')
        df51.insert(7, 'To', 'FPS')
        df51.insert(8, 'To_State', 'Chhattisgarh')
        df51.insert(9, 'commodity', 'Wheat')
  
        df51.rename(columns={
            'WH_ID': 'From_ID',
            'WH_Name': 'From_Name',
            'WH_Lat': 'From_Lat',
            'WH_Long': 'From_Long',
            }, inplace=True)
        df51.rename(columns={
            'FPS_ID': 'To_ID',
            'FPS_Name': 'To_Name',
            'FPS_Lat': 'To_Lat',
            'FPS_Long': 'To_Long',
            'Allocation_Wheat': 'quantity',
            
            }, inplace=True)
        df51.rename(columns={'WH_District': 'From_District',
                   'FPS_District': 'To_District'}, inplace=True)
        df51 = df51.loc[:, [
            'Scenario',
            'From',
            'From_State',
            'From_District',
            'From_ID',
            'From_Name',
            'From_Lat',
            'From_Long',
            'To',
            'To_ID',
            'To_Name',
            'To_State',
            'To_District',
            'To_Lat',
            'To_Long',
            'commodity',
            'quantity',
            ]]
        
        
        df41 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        #df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        df41 = df41[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'FPS_ID',
            'Values',
            ]]
        df41 = pd.merge(df41, FPS, on='FPS_ID', how='inner')
        df511 = df4[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'FPS_ID',
            'FPS_Name',
            'FPS_District',
            'FPS_Lat',
            'FPS_Long',
            'Allocation_Rice',
            ]]
        df511.insert(0, 'Scenario', 'Optimized')
        df511.insert(1, 'From', 'Depot')
        df511.insert(2, 'From_State', 'Chhattisgarh')
        df511.insert(7, 'To', 'FPS')
        df511.insert(8, 'To_State', 'Chhattisgarh')
        df511.insert(9, 'commodity', 'Rice')
  
        df511.rename(columns={
            'WH_ID': 'From_ID',
            'WH_Name': 'From_Name',
            'WH_Lat': 'From_Lat',
            'WH_Long': 'From_Long',
            }, inplace=True)
        df511.rename(columns={
            'FPS_ID': 'To_ID',
            'FPS_Name': 'To_Name',
            'FPS_Lat': 'To_Lat',
            'FPS_Long': 'To_Long',
            'Allocation_Rice': 'quantity',
            
            }, inplace=True)
        df511.rename(columns={'WH_District': 'From_District',
                   'FPS_District': 'To_District'}, inplace=True)
        df511= df511.loc[:, [
            'Scenario',
            'From',
            'From_State',
            'From_District',
            'From_ID',
            'From_Name',
            'From_Lat',
            'From_Long',
            'To',
            'To_ID',
            'To_Name',
            'To_State',
            'To_District',
            'To_Lat',
            'To_Long',
            'commodity',
            'quantity',
            ]]
        def convert_to_numeric(value):
            try:
                return pd.to_numeric(value)
            except ValueError:
                return value
                
        df_combined = pd.concat([df51, df511])
        df_combined1 = df_combined[df_combined['quantity'] != 0]
        df_combined1['From_ID'] = df_combined1['From_ID'].apply(convert_to_numeric)
        df_combined1['To_ID'] = df_combined1['To_ID'].apply(convert_to_numeric)
        
        
        # Save DataFrame to Excel
        file_path = 'Backend/Tagging_Sheet_Pre11.xlsx'  # Adjust the path as needed
        df_combined1.to_excel(file_path, sheet_name='BG_FPS1', index=False, engine='xlsxwriter')
        
        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)       
            
        input = pd.ExcelFile('Backend/Data_1.xlsx')
        node1 = pd.read_excel(input,sheet_name="A.1 Warehouse")
        node1["concatenate"]= node1['WH_Lat'].astype(str) + ',' + node1['WH_Long'].astype(str)
        node2 = pd.read_excel(input,sheet_name="A.2 FPS")
        node2["concatenate1"]= node2['FPS_Lat'].astype(str) + ',' + node2['FPS_Long'].astype(str)
        #Distance = pd.ExcelFile('Backend//Distance_Initial_L2.xlsx')
        DistanceBing = read_protected_excel('Backend//Distance_Initial_L2.xlsx', 'distf', sheet_name='BG_BG')
        Warehouse = read_protected_excel('Backend//Distance_Initial_L2.xlsx', 'distf', sheet_name='Warehouse')
        FPS = read_protected_excel('Backend//Distance_Initial_L2.xlsx', 'distf', sheet_name='FPS')
        node1 = node1[['WH_ID', 'WH_Lat', 'WH_Long','concatenate']].copy()
        node1['WH_ID'] = node1['WH_ID'].astype(str)
        Warehouse['WH_ID'] = Warehouse['WH_ID'].astype(str)
        War = pd.merge(node1, Warehouse, on='WH_ID')
        df1_w = War[War['concatenate'] != War['Lat_Long']]
        Warehouse_ID = df1_w['WH_ID'].unique()
        node2 = node2[['FPS_ID', 'FPS_Lat', 'FPS_Long','concatenate1']].copy()
        node2['FPS_ID'] = node2['FPS_ID'].astype(str)
        FPS['FPS_ID'] = FPS['FPS_ID'].astype(str)
        FPS1 = pd.merge(node2, FPS, on='FPS_ID')
        df1_f = FPS1[FPS1['concatenate1'] != FPS1['Lat_Long']]
        FPS_ID = df1_f['FPS_ID'].unique()
        BG_BG = DistanceBing
        Distance1 = BG_BG.drop(columns=BG_BG.columns[BG_BG.columns.isin(Warehouse_ID)])
        Distance2 =Distance1.T
        Distance3 = Distance2.drop(columns=Distance2.columns[Distance2.columns.isin(FPS_ID)])
        Distance3 = Distance3.T
        with pd.ExcelWriter('Backend//Chattisgarh_Distance_L2.xlsx') as writer:
            Distance3.to_excel(writer, sheet_name='BG_BG', index=False)
            
        
        
        
   
        
        Cost = pd.ExcelFile("Backend//Chattisgarh_Distance_L2.xlsx")
        BG_BG = pd.read_excel(Cost,sheet_name="BG_BG")
        Cost.close()
        data1 = pd.ExcelFile("Backend//Tagging_Sheet_Pre11.xlsx")
        df5 = pd.read_excel(data1,sheet_name="BG_FPS1")
        data1.close()

        Distance_BG_BG = {}
        column_list_BG_BG = list(BG_BG.columns.astype(str))
        row_list_BG_BG = list(BG_BG.iloc[:, 0].astype(str))

        for ind in df5.index:
            from_code = df5['From_ID'][ind]
            to_code = df5['To_ID'][ind]
            from_code_str = str(from_code)
            to_code_str = str(to_code)
            
            if to_code_str in row_list_BG_BG and from_code_str in column_list_BG_BG:
                index_i = row_list_BG_BG.index(to_code_str)
                index_j = column_list_BG_BG.index(from_code_str)
                key = to_code_str + "_" + from_code_str
                Distance_BG_BG[key] = BG_BG.iloc[index_i, index_j]

                
        df5["Tagging"] = df5['To_ID'].astype(str) + '_' + df5['From_ID'].astype(str)
        df5['Distance'] = df5['Tagging'].map(Distance_BG_BG)
        df5.fillna('shallu', inplace=True)
        df5.to_excel('Backend//Result_Sheet12.xlsx', sheet_name='Warehouse_FPS', index=False)

        # Result_Sheet1=pd.ExcelFile("Backend//Result_Sheet12.xlsx")
        # df6= pd.read_excel(Result_Sheet1,sheet_name="Warehouse_FPS")
       
        # df7=df6.loc[df6['Distance'] == "shallu"]
        # source3 = df7['From_ID']  # FCI is the source and FPS is the destination
        # destination3 = df7['To_ID']
        # BingMapsKey = "Am90IYBtKbMlT0MxGO2UZ25Ch1v8ATm4rMrPscWAmfN0xED8mIbvTrH5WUFfDQoq"  # Bing Map Key
        # df7["Warehouse_lat_long"]= df7['From_Lat'].astype(str) + ',' + df7['From_Long'].astype(str)
        # df7["FPS_lat_long"]= df7['To_Lat'].astype(str) + ',' + df7['To_Long'].astype(str)

        # #df8=df7["From_ID","To_ID","Warehouse_lat_long","FPS_lat_long"]
        # df8 = df7[['From_ID', 'To_ID', 'Warehouse_lat_long', 'FPS_lat_long']]
        # source3 = df8['From_ID']
        # destination3 = df8['To_ID']
        # dist3 = [0 for _ in range(len(destination3))]  # Transport matrix for FCI_FPS
        # BingMapsKey = "Am90IYBtKbMlT0MxGO2UZ25Ch1v8ATm4rMrPscWAmfN0xED8mIbvTrH5WUFfDQoq"  # Bing Map Key

        # dist3 = []  # Initialize an empty list for distances

        # for index, row in df8.iterrows():
        #     origin = row["Warehouse_lat_long"]
        #     dest = row["FPS_lat_long"]
        #     max_retries = 3
        #     retries = 0
        #     while retries < max_retries:
        #         try:
        #             response = requests.get(
        #                 "https://dev.virtualearth.net/REST/v1/Routes/DistanceMatrix?origins=" + origin + "&destinations=" + dest +
        #                 "&travelMode=driving&key=" + BingMapsKey)
        #             resp = response.json()

        #             # Append a new element to dist3 for the current index
        #             dist3.append(resp['resourceSets'][0]['resources'][0]['results'][0]['travelDistance'])

        #             # Display the output for each iteration
        #             print(f"Origin: {origin}, Destination: {dest}, Distance: {dist3[-1]}")
        #             break  # Successful response, exit the retry loop
        #         except (requests.ConnectionError, requests.Timeout):
        #             retries += 1
        #             print(f"Attempt {retries} failed. Retrying...")
        #             time.sleep(1)  # Wait for 1 second before retrying

        # print("Final distances:", dist3)

        # if stop_process==True:
        #     data = {}
        #     data['status'] = 0
        #     data['message'] = "Process Stopped"
        #     json_data = json.dumps(data)
        #     json_object = json.loads(json_data)
        #     return json.dumps(json_object, indent=1)

        # df7["Distance"]=dist3
        # df7.drop(['Warehouse_lat_long', 'FPS_lat_long'], axis=1)
        # df9=df6.loc[df6['Distance'] != "shallu"]
        # df9 = df9.loc[:, [
        #         'Scenario',
        #         'From',
        #         'From_State',
        #         'From_District',
        #         'From_ID',
        #         'From_Name',
        #         'From_Lat',
        #         'From_Long',
        #         'To',
        #         'To_ID',
        #         'To_Name',
        #         'To_State',
        #         'To_District',
        #         'To_Lat',
        #         'To_Long',
        #         'commodity',
        #         'quantity',
        #          "Distance",]]
        # df7 = df7.loc[:, [
        #         'Scenario',
        #         'From',
        #         'From_State',
        #         'From_District',
        #         'From_ID',
        #         'From_Name',
        #         'From_Lat',
        #         'From_Long',
        #         'To',
        #         'To_ID',
        #         'To_Name',
        #         'To_State',
        #         'To_District',
        #         'To_Lat',
        #         'To_Long',
        #         'commodity',
        #         'quantity',
        #        "Distance"]]
        
        # print(df9.head())  # Print the first few rows
        # #df10 = df9.append([df7], ignore_index=True)
        # df10 = pd.concat([df9, df7], ignore_index=True)
        # #df10 = df9.append([df7],ignore_index=True)
        # result = ((df10['quantity']) * df10['Distance']).sum()
        # print(result)
        
        # df10.to_excel('Backend//Result_Sheet.xlsx',
        #              sheet_name='Warehouse_FPS')

# ----------------------------------------------------------------------------------------------------------------------------------------------
        Result_Sheet1 = pd.ExcelFile("Backend//Result_Sheet12.xlsx")
        df6 = pd.read_excel(Result_Sheet1, sheet_name="Warehouse_FPS")

        # Filter rows where Distance == 'shallu'
        df7 = df6.loc[df6['Distance'] == "shallu"]

        auth_url = 'https://kerala.pmgatishakti.gov.in/PMGatishaktiApiService/authenticate'
        distance_url = 'https://kerala.pmgatishakti.gov.in/PMGatishaktiApiService/dfpdapi/roaddistance'

        auth_payload = {
            "username": "DFPD_C",
            "password": "W9Vtb8WKkt3"
        }

        FILE_PATH = 'distanceIndent.json'

        # 10 minutes

        def get_token():
            """Authenticate and return cached Gatishakti token (refreshes after 10 minutes)."""
            global auth_token, token_timestamp
            current_time = time.time()

            # ✅ Reuse existing valid token
            if auth_token and (current_time - token_timestamp) < TOKEN_VALIDITY_SECONDS:
                return auth_token

            # 🔄 Generate a new one if expired or missing
            try:
                response = requests.post(auth_url, json=auth_payload, timeout=20)
                if response.status_code == 200:
                    token = response.json().get('token')
                    if token:
                        auth_token = token
                        token_timestamp = current_time
                        print("🔐 Token generated successfully.")
                        return token
                    else:
                        print(" Token missing in response.")
                else:
                    print(f"Failed to get token: {response.status_code}")
            except Exception as e:
                print(f"Error getting token: {e}")

            return False

        def process_batch(df_batch):
            """Send multiple rows in one Gatishakti request."""
            token = get_token()
            if not token:
                print("⚠️ No token received — batch skipped.")
                return None

            time.sleep(5)  # avoid rate limit
            headers = {'Authorization': f'Bearer {token}'}

            data = {
                "parameter": [{
                    "src_lng": row["From_Long"],
                    "src_lat": row["From_Lat"],
                    "dest_lng": row["To_Long"],
                    "dest_lat": row["To_Lat"]
                } for _, row in df_batch.iterrows()]
            }

            with open(FILE_PATH, 'w') as f:
                json.dump(data, f, indent=4)

            with open(FILE_PATH, 'rb') as f:
                files = {'LatsLongsFile': f}
                try:
                    response = requests.post(distance_url, headers=headers, files=files, timeout=60)
                    return response
                except Exception as e:
                    print(f" Batch request failed: {e}")
                    return None

        def process_single(row):
            """Fetch distance for a single pair using Gatishakti. Returns distance or 0."""
            token = get_token()
            if not token:
                print(f" No token for From_ID={row['From_ID']} → distance set to 0")
                return 0

            time.sleep(2)
            headers = {'Authorization': f'Bearer {token}'}

            data = {
                "parameter": [{
                    "src_lng": row["From_Long"],
                    "src_lat": row["From_Lat"],
                    "dest_lng": row["To_Long"],
                    "dest_lat": row["To_Lat"]
                }]
            }

            with open(FILE_PATH, 'w') as f:
                json.dump(data, f, indent=4)

            with open(FILE_PATH, 'rb') as f:
                files = {'LatsLongsFile': f}
                try:
                    response = requests.post(distance_url, headers=headers, files=files, timeout=30)
                    if response.status_code == 200:
                        single_json = response.json()
                        if (
                            'data' in single_json and 
                            len(single_json['data']) > 0 and 
                            'distance' in single_json['data'][0]
                        ):
                            dist = single_json['data'][0]['distance']
                            print(f" From_ID={row['From_ID']} Distance={dist}")
                            return dist
                except Exception as e:
                    print(f" Error in single request ({row['From_ID']}): {e}")

            print(f"⚠️ No distance for From_ID={row['From_ID']} → set 0")
            return 0

        batch_size = 80
        total_rows = len(df7)
        num_batches = (total_rows + batch_size - 1) // batch_size

        dist3 = []

        for batch_num in range(num_batches):
            start_idx = batch_num * batch_size
            end_idx = min((batch_num + 1) * batch_size, total_rows)
            df_batch = df7.iloc[start_idx:end_idx]

            print(f"\n🚀 Processing batch {batch_num + 1}/{num_batches} (rows {start_idx + 1}-{end_idx})")

            response = process_batch(df_batch)

            if response and response.status_code == 200:
                response_json = response.json()

                #  If batch Gatishakti call works fine
                if 'data' in response_json and all('distance' in row_data for row_data in response_json['data']):
                    for row_data, (_, row) in zip(response_json['data'], df_batch.iterrows()):
                        distance = row_data['distance']
                        dist3.append(distance)
                        print(f"Batch distance for From_ID={row['From_ID']} → {distance}")
                else:
                    # Fallback to single calls if any missing
                    print("⚠️ Batch incomplete — retrying missing rows one by one...")
                    for i, (_, row) in enumerate(df_batch.iterrows()):
                        if (
                            'data' in response_json and 
                            i < len(response_json['data']) and 
                            'distance' in response_json['data'][i]
                        ):
                            dist = response_json['data'][i]['distance']
                            dist3.append(dist)
                        else:
                            distance = process_single(row)
                            dist3.append(distance if distance else 0)
            else:
                #  Batch failed completely
                print("Batch API failed — retrying all rows individually...")
                for _, row in df_batch.iterrows():
                    distance = process_single(row)
                    dist3.append(distance if distance else 0)


        df7["Distance"] = dist3

        # Merge with old data
        df9 = df6.loc[df6['Distance'] != "shallu"]
        df10 = pd.concat([df9, df7], ignore_index=True)

        # Compute total result
        df10["Distance"] = df10["Distance"].fillna(0)
        df10["quantity"] = df10["quantity"].fillna(0)
        result = ((df10['quantity']) * df10['Distance']).sum()

        # Save Excel
        # output_path = 'Backend//Result_Sheet_leg1.xlsx'
        df10.to_excel('Backend//Result_Sheet.xlsx', sheet_name='Warehouse_FPS')
# ----------------------------------------------------------------------------------------------------------

        data["Scenario"]="Inter"
        data["Scenario_Baseline"] = "Baseline"
        
        data["WH_Used"] = df5['From_ID'].nunique()
        data["WH_Used_Baseline"] = "198"
        
        data["FPS_Used"] = df5['To_ID'].nunique()
        data["FPS_Used_Baseline"] = "13,649"
        
        total_demand = pd.to_numeric(df10["quantity"], errors='coerce').fillna(0).sum()

        data['Demand'] = total_demand
        data['Demand_Baseline'] ="23,62,728"
        
        data['Total_QKM'] = float(result)
        data['Total_QKM_Baseline'] = "4.99.58.425"
        
        data['Average_Distance'] = (float(round(result, 2)) / total_demand)
        data['Average_Distance_Baseline'] = "21.14"

        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)                     

        save_to_database(month, year, day)
        save_monthly_data(month, year, day, float(result))
        
        def delete_files(file_paths):
            deleted_files = []
            failed_files = []
            
            for file_path in file_paths:
                try:
                    if os.path.exists(file_path):  # Check if the file exists
                        os.remove(file_path)  # Delete the file
                        deleted_files.append(file_path)  # Track successfully deleted files
                    else:
                        failed_files.append((file_path, "File does not exist"))
                except Exception as e:
                    failed_files.append((file_path, str(e)))  # Track failed deletions with error message
                    
            return deleted_files, failed_files
                       
                    

        # List of files to delete
        files_to_delete = [
            'Backend/Chattisgarh_Distance_L2.xlsx',
            'Backend/Result_Sheet12.xlsx',
            'Backend/Result_Sheet12',
            'Backend//Tagging_Sheet_Pre11.xlsx',
            
        ]

        # Call the function to delete the files
        delete_files(files_to_delete)
        
        json_data = json.dumps(data)
        json_object = json.loads(json_data)

        if os.path.exists('ouputPickle.pkl'):
            os.remove('ouputPickle.pkl')

        # open pickle file
        dbfile1 = open('ouputPickle.pkl', 'ab')

    # save pickle data
    pickle.dump(json_object, dbfile1)
    dbfile1.close()
    data['status'] = 1
    json_data = json.dumps(data)
    json_object = json.loads(json_data)
    return json.dumps(json_object, indent=1)
    
    
@app.route('/processFileleg1', methods=['POST'])
def processFile_leg1():
    global stop_process
    stop_process = False
    # CHANGE: if async=1, do not block this HTTP request;
    # spawn a background job and just return a job_id.
    if request.form.get("async") == "1":
        client_id = (
            request.form.get("client_id")
            or request.form.get("username")
            or request.form.get("user")
            or ""
        )
        if not client_id:
            client_id = "anonymous"
        job_id = _job_create(client_id, endpoint="/processFileleg1", message="queued")
        form_dict = request.form.to_dict(flat=True)
        # CHANGE: run heavy optimization in a separate OS process
        p = multiprocessing.Process(target=_run_processfileLeg1_in_background, args=(job_id, form_dict), daemon=True)
        p.start()
        return jsonify({"status": 1, "job_id": job_id, "message": "processing started"})
    # END CHANGE (async start mode)
    scenario_type = request.form.get('type')
    '''scenario_type="Intra"'''
    if scenario_type == "Intra":
        message = 'DataFile file is incorrect'
        try:
            USN = pd.ExcelFile('Backend//Data_2.xlsx')
            month = request.form.get('month')        
            year = request.form.get('year')        
            day = request.form.get('day')
        except Exception as e:
            data = {}
            data['status'] = 0
            data['message'] = message
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        input = pd.ExcelFile('Backend//Data_2.xlsx')
        node1 = pd.read_excel(input,sheet_name="A.2 FCI")
        node2 = pd.read_excel(input,sheet_name="A.1 Warehouse")

        dist = [[0 for a in range(len(node2["SW_ID"]))] for b in range(len(node1["WH_ID"]))]
        phi_1 = []
        phi_2 = []
        delta_phi = []
        delta_lambda = []
        R = 6371 

        for i in node1.index:
            for j in node2.index:
                phi_1=math.radians(node1["WH_Lat"][i])
                phi_2=math.radians(node2["SW_lat"][j])
                delta_phi=math.radians(node2["SW_lat"][j]-node1["WH_Lat"][i])
                delta_lambda=math.radians(node2["SW_Long"][j]-node1["WH_Long"][i])
                x=math.sin(delta_phi / 2.0) ** 2 + math.cos(phi_1) * math.cos(phi_2) * math.sin(delta_lambda / 2.0) ** 2
                y=2 * math.atan2(math.sqrt(x), math.sqrt(1 - x))
                dist[i][j]=R*y
                
        dist=np.transpose(dist)
        df3 = pd.DataFrame(data = dist, index = node2['SW_ID'], columns = node1['WH_ID'])
        df3.to_excel('Backend//Distance_Matrix_Leg1.xlsx', index=True)

        WKB = excelrd.open_workbook('Backend//Distance_Matrix_Leg1.xlsx')
        Sheet1 = WKB.sheet_by_index(0)
        FCI = pd.read_excel(USN, sheet_name='A.2 FCI', index_col=None)
        WH = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
        print('Avvvvvvv')
        FCI['WH_District'] = FCI['WH_District'].apply(lambda x: x.replace(' ', ''))
        WH['SW_District'] = WH['SW_District'].apply(lambda x: x.replace(' ', ''))
        

        Warehouse_No = []
        FPS_No = []
        Warehouse_No = FCI['WH_ID'].nunique()
        FPS_No = WH['SW_ID'].nunique()
        Warehouse_Count = {}

        FPS_Count = {}
        Warehouse_Count['Warehouse_Count'] = Warehouse_No
        FPS_Count['FPS_Count'] = FPS_No  # No of FPS

        Total_Supply = []
        Total_Supply_Warehouse = {}
        Total_Supply = FCI['Storage_Capacity'].sum()
        Total_Supply_Warehouse['Total_Supply_Warehouse'] = Total_Supply  # Total SUPPLY

        Total_Demand = []
        Total_Demand_FPS = {}
        Total_Demand = WH['Demand'].sum()
        Total_Demand_FPS['Total_Demand_Warehouse'] = Total_Demand  # Total demand

        
        District_Capacity = {}
        for i in range(len(FCI['WH_District'])):
            District_Name = FCI['WH_District'][i]
            if District_Name not in District_Capacity:
                District_Capacity[District_Name] = FCI['Storage_Capacity'][i]
            else:
                District_Capacity[District_Name] = FCI['Storage_Capacity'][i] + District_Capacity[District_Name]

        District_Demand = {}
        for i in range(len(WH['SW_District'])):
            District_Name_FPS = WH['SW_District'][i]
            if District_Name_FPS not in District_Demand:
                District_Demand[District_Name_FPS] = WH['Demand'][i]
            else:
                District_Demand[District_Name_FPS] = WH['Demand'][i] + District_Demand[District_Name_FPS]
        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        
        model = LpProblem('Supply-Demand-Problem', LpMinimize)

        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        Variable1 = []
        
        for i in range(len(FCI['WH_ID'])):
            for j in range(len(WH['SW_ID'])):
                Variable1.append(str(FCI['WH_ID'][i]) + '_'
                                 + str(FCI['WH_District'][i]) + '_'
                                 + str(WH['SW_ID'][j]) + '_'
                                 + str(WH['SW_District'][j]) + '_Wheat')

        # Variables for Wheat from lEVEL2 TO FPS

        DV_Variables1 = LpVariable.matrix('X', Variable1, cat='float',
                lowBound=0)
        Allocation1 = np.array(DV_Variables1).reshape(len(FCI['WH_ID']),
                len(WH['SW_ID']))

        
        
        District_Capacity = {}
        for i in range(len(FCI["WH_District"])):
            District_Name = FCI["WH_District"][i]
            if District_Name not in District_Capacity:
                District_Capacity[District_Name] = float(FCI["Storage_Capacity"][i])
            else:
                District_Capacity[District_Name] += float(FCI["Storage_Capacity"][i])
 
        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        District_Demand = {}
        for i in range(len(WH["SW_District"])):
            District_Name_FPS = WH["SW_District"][i]
            if District_Name_FPS not in District_Demand:
                District_Demand[District_Name_FPS] = float(WH["Demand"][i])
            else:
                District_Demand[District_Name_FPS] += float(WH["Demand"][i])
        District_Name = []
        District_Name2=[]
        District_Name = [i for i in District_Demand if i not in District_Capacity]
        District_Name4 = [i for i in District_Capacity if i not in District_Demand]
        District_Name2 = [i for i in District_Demand if i in District_Capacity and District_Demand[i] >= District_Capacity[i]]
        District_Name_1 = {}
        District_Name_1['District_Name_All'] = District_Name + District_Name2
        District_Name3 = [i for i in District_Demand if i in District_Capacity and District_Demand[i] <= District_Capacity[i]]
        
        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)        
        name1 = []
        lst1 = []
        for j in range(len(DV_Variables1)):
            name1 = str(DV_Variables1[j])
            lst1 = name1.split("_")
            if lst1[2] in District_Name3 and lst1[4] in District_Name3 and lst1[2]!=lst1[4]:
                model+=DV_Variables1[j]==0
                #print(DV_Variables1[j]==0)
                
        name2 = []
        lst2 = []
        for j in range(len(DV_Variables1)):
            name2 = str(DV_Variables1[j])
            lst2 = name2.split("_")
            if lst2[2] in District_Name2 and lst2[4] in District_Name3:
                model+=DV_Variables1[j]==0
                #print(DV_Variables1[j]==0)
                
        name3 = []
        lst3 = []
        for j in range(len(DV_Variables1)):
            name3 = str(DV_Variables1[j])
            lst3 = name3.split("_")
            if lst3[2] in District_Name2 and lst3[4] in District_Name2 and lst3[2]!=lst3[4]:
                model+=DV_Variables1[j]==0
                #print(DV_Variables1[j]==0)

        name4 = []
        lst4 = []
        for j in range(len(DV_Variables1)):
            name4 = str(DV_Variables1[j])
            lst4 = name4.split("_")
            if lst4[2] in District_Name4 and lst4[4] in District_Name3:
                model+=DV_Variables1[j]==0
                #print(DV_Variables1[j]==0)

        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)


        PC_Mill = []
        for col in range(Sheet1.nrows):
            if col==0:
                continue
            temp = []
            for row in range (Sheet1.ncols):
                if row==0:
                    continue
                temp.append(Sheet1.cell_value(col,row))
            PC_Mill.append(temp)

        FCI_WH = [[ PC_Mill[j][i] for j in range(len( PC_Mill))] for i in range(len( PC_Mill[0]))]

        allCombination1 = []

        for i in range(len(FCI_WH)):
            for j in range(len(WH['SW_ID'])):
                allCombination1.append(Allocation1[i][j] * FCI_WH[i][j])

        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        model += lpSum(allCombination1)

        # Demand Constraints for Wheat

        for i in range(len(WH['SW_ID'])):
            model += lpSum(Allocation1[j][i] for j in range(len(FCI['WH_ID'
                           ]))) >= WH['Demand'][i]

        # Supply Constraints for Warehouses

        for i in range(len(FCI['WH_ID'])):
            model += lpSum(Allocation1[i][j] for j in range(len(WH['SW_ID'
                           ]))) <= FCI['Storage_Capacity'][i]

       # Calling CBC_CMB Solver

        #model.solve(CPLEX_CMD(options=['set mip tolerances mipgap 0.01']))
        #model.prob.solve(CPLEX_CMD(options=["set mip tolerances mipgap 0.03","set emphasis memory y"]))
        #model.solve(CPLEX_CMD(options=['set mip tolerances mipgap 0.03',"set emphasis memory y"]))
        model.solve(CPLEX_CMD(options=["set mip tolerances mipgap 0.01","set emphasis memory y","set mip strategy file 3","set workmem 2048"]))
        
        status = LpStatus[model.status]
        if status == LpStatusInfeasible or status == LpStatusUnbounded or status == LpStatusNotSolved or status == LpStatusUndefined:
           print("Problem is infeasible or unbounded.")
           data = {}
           data['status'] = 0
           data['message'] = "Infeasible or Unbounded Solution"
           json_data = json.dumps(data)
           json_object = json.loads(json_data)
           return json.dumps(json_object, indent=1)
 
        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        #model.solve(PULP_CBC_CMD())
        
        
        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
        

        data = {}
        #data['status'] = 1
        #data['modelStatus'] = Status
        #data['totalCost'] = float(round(model.objective.value(),1))
        #data['original'] = float(round(total, 2))
        #data['percentageReduction'] = float(round((total
                #- model.objective.value()) / total, 4) * 100)
        #data['Average_Distance'] = float(round(model.objective.value(), 2)) / Total_Demand
        #data['Demand'] = int(FPS['Allocation_Wheat'].sum())

        
        Output_File = open('Backend//Inter_District1_leg1.csv', 'w')
        for v in model.variables():
            if v.value() > 0:
                Output_File.write(v.name + '\t' + str(v.value()) + '\n')

        Output_File = open('Backend//Inter_District1_leg1.csv', 'w')
        for v in model.variables():
            if v.value() > 0:
                Output_File.write(v.name + '\t' + str(v.value()) + '\n')


        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)

        df9 = pd.read_csv('Backend//Inter_District1_leg1.csv',header=None)
        df9.columns = ['Tagging']
        df9[[
            'Var',
            'WH_ID',
            'W_D',
            'SW_ID',
            'SW_D',
            'commodity_Value',
            ]] = df9[df9.columns[0]].str.split('_', n=6, expand=True)
        del df9[df9.columns[0]]
        df9[['commodity', 'Values']] = df9['commodity_Value'
                ].str.split('\\t', n=1, expand=True)
        del df9['commodity_Value']
        df9 = df9.drop(np.where(df9['commodity'] == 'Wheat1')[0])
        df9.to_excel('Backend//Tagging_Sheet_Pre_leg1.xlsx', sheet_name='BG_FPS')
        df31 = pd.read_excel('Backend//Tagging_Sheet_Pre_leg1.xlsx')
        df31['WH_ID'] = df31['WH_ID'].astype(str)  # Convert to object type, adjust as needed
        FCI['WH_ID'] = FCI['WH_ID'].astype(str) 

        df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        #df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
        df4 = df4[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'SW_ID',
            'Values',
            ]]
        df4 = pd.merge(df4, WH, on='SW_ID', how='inner')
        df51 = df4[[
            'WH_ID',
            'WH_Name',
            'WH_District',
            'WH_Lat',
            'WH_Long',
            'SW_ID',
            'SW_Name',
            'SW_District',
            'SW_lat',
            'SW_Long',
            'Values',
            ]]
        df51.insert(0, 'Scenario', 'Optimized')
        df51.insert(1, 'From', 'Depot')
        df51.insert(2, 'From_State', 'Nagaland')
        df51.insert(7, 'To', 'FPS')
        df51.insert(8, 'To_State', 'Nagaland')
        df51.insert(9, 'commodity', 'Wheat')
        df51.rename(columns={
            'WH_ID': 'From_ID',
            'WH_Name': 'From_Name',
            'WH_Lat': 'From_Lat',
            'WH_Long': 'From_Long',
            }, inplace=True)
        df51.rename(columns={
            'SW_ID': 'To_ID',
            'SW_Name': 'To_Name',
            'SW_lat': 'To_Lat',
            'SW_Long': 'To_Long',
            'Values' :'quantity'
            }, inplace=True)
        df51.rename(columns={'WH_District': 'From_District',
                   'SW_District': 'To_District'}, inplace=True)
        df51 = df51.loc[:, [
            'Scenario',
            'From',
            'From_State',
            'From_District',
            'From_ID',
            'From_Name',
            'From_Lat',
            'From_Long',
            'To',
            'To_ID',
            'To_Name',
            'To_State',
            'To_District',
            'To_Lat',
            'To_Long',
            'commodity',
            'quantity',
            ]]
        
        df51.to_excel('Backend//Tagging_Sheet_Pre11_leg1.xlsx', sheet_name='BG_FPS1')
        
        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)       
        
        data1 = pd.ExcelFile("Backend//Tagging_Sheet_Pre11_leg1.xlsx")
        df5 = pd.read_excel(data1,sheet_name="BG_FPS1")

        Cost = pd.ExcelFile("Backend//Nagaland_Distance_L1.xlsx")
        BG_BG = pd.read_excel(Cost,sheet_name="BG_BG")
        
        Distance_BG_BG = {}
        column_list_BG_BG = list(BG_BG.columns)
        #print(column_list_BG_BG)
        row_list_BG_BG = list(BG_BG.iloc[:, 0])
        #print(row_list_BG_BG )  
        for ind in df5.index:
            from_code= df5['From_ID'][ind] 
            to_code = df5['To_ID'][ind]
            if to_code in row_list_BG_BG and from_code in column_list_BG_BG:
                index_i = row_list_BG_BG.index(to_code)
                index_j = column_list_BG_BG.index(from_code)
                key = str(to_code) + "_" + str(from_code)
                Distance_BG_BG[key]= BG_BG.iloc[index_i , index_j]
                print(Distance_BG_BG[key])

        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)            
        
        #df5["Tagging"]=df5['To_ID']+ '_' + df5['From_ID']
        df5["Tagging"] = df5['To_ID'].astype(str) + '_' + df5['From_ID'].astype(str)
        df5['Distance'] = df5['Tagging'].map(Distance_BG_BG)
        df5 = df5.replace('',pd.NaT).fillna('shallu')
        d5=df5.loc[df5['Distance'] == "shallu"]
        df5.to_excel('Backend//Result_Sheet12.xlsx',
                         sheet_name='Warehouse_FPS')

       

# ----------------------------------------------------------------------------------------------------------------------------------------------
        Result_Sheet1 = pd.ExcelFile("Backend//Result_Sheet12.xlsx")
        df6 = pd.read_excel(Result_Sheet1, sheet_name="Warehouse_FPS")

        # Filter rows where Distance == 'shallu'
        df7 = df6.loc[df6['Distance'] == "shallu"]

        auth_url = 'https://kerala.pmgatishakti.gov.in/PMGatishaktiApiService/authenticate'
        distance_url = 'https://kerala.pmgatishakti.gov.in/PMGatishaktiApiService/dfpdapi/roaddistance'

        auth_payload = {
            "username": "DFPD_C",
            "password": "W9Vtb8WKkt3"
        }

        FILE_PATH = 'distanceIndent.json'

        # 10 minutes

        def get_token():
            """Authenticate and return cached Gatishakti token (refreshes after 10 minutes)."""
            global auth_token, token_timestamp
            current_time = time.time()

            # ✅ Reuse existing valid token
            if auth_token and (current_time - token_timestamp) < TOKEN_VALIDITY_SECONDS:
                return auth_token

            # 🔄 Generate a new one if expired or missing
            try:
                response = requests.post(auth_url, json=auth_payload, timeout=20)
                if response.status_code == 200:
                    token = response.json().get('token')
                    if token:
                        auth_token = token
                        token_timestamp = current_time
                        print("🔐 Token generated successfully.")
                        return token
                    else:
                        print(" Token missing in response.")
                else:
                    print(f"Failed to get token: {response.status_code}")
            except Exception as e:
                print(f"Error getting token: {e}")

            return False

        def process_batch(df_batch):
            """Send multiple rows in one Gatishakti request."""
            token = get_token()
            if not token:
                print("⚠️ No token received — batch skipped.")
                return None

            time.sleep(5)  # avoid rate limit
            headers = {'Authorization': f'Bearer {token}'}

            data = {
                "parameter": [{
                    "src_lng": row["From_Long"],
                    "src_lat": row["From_Lat"],
                    "dest_lng": row["To_Long"],
                    "dest_lat": row["To_Lat"]
                } for _, row in df_batch.iterrows()]
            }

            with open(FILE_PATH, 'w') as f:
                json.dump(data, f, indent=4)

            with open(FILE_PATH, 'rb') as f:
                files = {'LatsLongsFile': f}
                try:
                    response = requests.post(distance_url, headers=headers, files=files, timeout=60)
                    return response
                except Exception as e:
                    print(f" Batch request failed: {e}")
                    return None

        def process_single(row):
            """Fetch distance for a single pair using Gatishakti. Returns distance or 0."""
            token = get_token()
            if not token:
                print(f" No token for From_ID={row['From_ID']} → distance set to 0")
                return 0

            time.sleep(2)
            headers = {'Authorization': f'Bearer {token}'}

            data = {
                "parameter": [{
                    "src_lng": row["From_Long"],
                    "src_lat": row["From_Lat"],
                    "dest_lng": row["To_Long"],
                    "dest_lat": row["To_Lat"]
                }]
            }

            with open(FILE_PATH, 'w') as f:
                json.dump(data, f, indent=4)

            with open(FILE_PATH, 'rb') as f:
                files = {'LatsLongsFile': f}
                try:
                    response = requests.post(distance_url, headers=headers, files=files, timeout=30)
                    if response.status_code == 200:
                        single_json = response.json()
                        if (
                            'data' in single_json and 
                            len(single_json['data']) > 0 and 
                            'distance' in single_json['data'][0]
                        ):
                            dist = single_json['data'][0]['distance']
                            print(f" From_ID={row['From_ID']} Distance={dist}")
                            return dist
                except Exception as e:
                    print(f" Error in single request ({row['From_ID']}): {e}")

            print(f"⚠️ No distance for From_ID={row['From_ID']} → set 0")
            return 0

        batch_size = 80
        total_rows = len(df7)
        num_batches = (total_rows + batch_size - 1) // batch_size

        dist3 = []

        for batch_num in range(num_batches):
            start_idx = batch_num * batch_size
            end_idx = min((batch_num + 1) * batch_size, total_rows)
            df_batch = df7.iloc[start_idx:end_idx]

            print(f"\n🚀 Processing batch {batch_num + 1}/{num_batches} (rows {start_idx + 1}-{end_idx})")

            response = process_batch(df_batch)

            if response and response.status_code == 200:
                response_json = response.json()

                #  If batch Gatishakti call works fine
                if 'data' in response_json and all('distance' in row_data for row_data in response_json['data']):
                    for row_data, (_, row) in zip(response_json['data'], df_batch.iterrows()):
                        distance = row_data['distance']
                        dist3.append(distance)
                        print(f"Batch distance for From_ID={row['From_ID']} → {distance}")
                else:
                    # Fallback to single calls if any missing
                    print("⚠️ Batch incomplete — retrying missing rows one by one...")
                    for i, (_, row) in enumerate(df_batch.iterrows()):
                        if (
                            'data' in response_json and 
                            i < len(response_json['data']) and 
                            'distance' in response_json['data'][i]
                        ):
                            dist = response_json['data'][i]['distance']
                            dist3.append(dist)
                        else:
                            distance = process_single(row)
                            dist3.append(distance if distance else 0)
            else:
                #  Batch failed completely
                print("Batch API failed — retrying all rows individually...")
                for _, row in df_batch.iterrows():
                    distance = process_single(row)
                    dist3.append(distance if distance else 0)


        df7["Distance"] = dist3

        # Merge with old data
        df9 = df6.loc[df6['Distance'] != "shallu"]
        df10 = pd.concat([df9, df7], ignore_index=True)

        # Compute total result
        df10["Distance"] = df10["Distance"].fillna(0)
        df10["quantity"] = df10["quantity"].fillna(0)
        result = ((df10['quantity']) * df10['Distance']).sum()

        # Save Excel
        # output_path = 'Backend//Result_Sheet_leg1.xlsx'
        df10.to_excel('Backend//Result_Sheet_leg1.xlsx', sheet_name='FCI_Warehouse')
# ----------------------------------------------------------------------------------------------------------
        
        data["Scenario"]="Intra"
        data["Scenario_Baseline"] = "Baseline"
        
        data["WH_Used"] = df5['From_ID'].nunique()
        data["WH_Used_Baseline"] = "76"
        
        data["FPS_Used"] = df5['To_ID'].nunique()
        data["FPS_Used_Baseline"] = "1,795"
        
        data['Demand'] = float(WH['Demand'].sum())
        data['Demand_Baseline'] = "69,247"
        result1 = ((dfinal['quantity']) * dfinal['Distance']).sum()
        data['Total_QKM'] = float(result1)
        data['Total_QKM_Baseline'] = "18,40,201"
        
        data['Average_Distance'] = float(round(result, 2)) / Total_Demand
        data['Average_Distance_Baseline'] = "26.58"

        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)                     

        save_to_database_leg1(month, year, day)
        save_monthly_data_leg1(month, year, day, float(result))
      
        json_data = json.dumps(data)
        json_object = json.loads(json_data)

        if os.path.exists('ouputPickle.pkl'):
            os.remove('ouputPickle.pkl')

        # open pickle file
        dbfile1 = open('ouputPickle.pkl', 'ab')
        
    else:
        message = 'DataFile file is incorrect'
        try:
            USN = pd.ExcelFile('Backend//Data_2.xlsx')
            month = request.form.get('month')        
            year = request.form.get('year')        
            day = request.form.get('day')
            scenario_type = request.form.get('type')
        except Exception as e:
            data = {}
            data['status'] = 0
            data['message'] = message
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)
            
        input = pd.ExcelFile('Backend//Data_2.xlsx')
        
        node1 = pd.read_excel(input,sheet_name="A.2 FCI")
        node2 = pd.read_excel(input,sheet_name="A.1 Warehouse")
        
          # ================= READ INPUT =================
        
        print("shallu before")
        input_file = 'Backend//Data_1.xlsx'
        input1 = pd.ExcelFile(input_file)

        FCI = pd.read_excel(input1, sheet_name='A.1 Warehouse')
        FPS = pd.read_excel(input1, sheet_name='A.2 FPS')
        
        
        
       

        # ================= CHECK CONDITION =================
        if FPS['Wheat_Procure'].sum() > 0:
            

            # ================= CLEAN DISTRICTS =================
            FCI['Storage_Point_P'] = FCI['Storage_Point_P'].astype(str).str.replace(' ', '').str.lower()
            FPS['Storage_Point_W'] = FPS['Storage_Point_W'].astype(str).str.replace(' ', '').str.lower()

            # ================= FIND COMMON DISTRICTS =================
            districts = list(set(FCI['Storage_Point_P']).intersection(set(FPS['Storage_Point_W'])))

            columns_18 = [
                'Scenario','From','From_State','From_District','From_ID','From_Name','From_Lat','From_Long',
                'To','To_ID','To_Name','To_State','To_District','To_Lat','To_Long','Commodity','Quantity'
            ]

            # =========================================================
            # ================= DISTRICT OPT ===========================
            # =========================================================
            if len(districts) > 0:

                final_df = pd.DataFrame()

                for dist_name in districts:
                     
                    print(f"Running for district: {dist_name}")
 

                    FCI_d = FCI[FCI['Storage_Point_P'] == dist_name].reset_index(drop=True)
                    FPS_d = FPS[FPS['Storage_Point_W'] == dist_name].reset_index(drop=True)
                    

                    if len(FCI_d) == 0 or len(FPS_d) == 0:
                        continue

                    # DISTANCE
                    R = 6371
                    dist = [[0]*len(FPS_d) for _ in range(len(FCI_d))]

                    for i in FCI_d.index:
                        for j in FPS_d.index:
                            phi_1 = math.radians(FCI_d["PC_Lat"][i])
                            phi_2 = math.radians(FPS_d["SW_Lat"][j])

                            dphi = math.radians(FPS_d["SW_Lat"][j] - FCI_d["PC_Lat"][i])
                            dlambda = math.radians(FPS_d["SW_Long"][j] - FCI_d["PC_Long"][i])

                            x = math.sin(dphi/2)**2 + math.cos(phi_1)*math.cos(phi_2)*math.sin(dlambda/2)**2
                            dist[i][j] = 2 * 6371 * math.atan2(math.sqrt(x), math.sqrt(1-x))

                    # MODEL
                    model = LpProblem(f'Supply_{dist_name}', LpMinimize)

                    Allocation = LpVariable.matrix(
                        'X',
                        [(i,j) for i in range(len(FCI_d)) for j in range(len(FPS_d))],
                        lowBound=0
                    )
                    Allocation = np.array(Allocation).reshape(len(FCI_d), len(FPS_d))

                    

                    model += lpSum(Allocation[i][j] * dist[i][j]
                                   for i in range(len(FCI_d))
                                   for j in range(len(FPS_d)))

                    for j in range(len(FPS_d)):
                        model += lpSum(Allocation[i][j] for i in range(len(FCI_d))) <= FPS_d['Available_Capacity'][j]

                    for i in range(len(FCI_d)):
                        model += lpSum(Allocation[i][j] for j in range(len(FPS_d))) == FCI_d['Wheat_Procure'][i]

                    

                    model.solve(PULP_CBC_CMD(msg=0, gapRel=0.03, timeLimit=600))
                    
                    

                    status = LpStatus[model.status]
                    if status not in ["Optimal", "Feasible"]:
                        print(f"Skipped {dist_name} - Status: {status}")
                        continue

                    # ================= EXTRACT RESULT =================
                    rows = []

                    for i in range(len(FCI_d)):
                        for j in range(len(FPS_d)):
                            val = Allocation[i][j].value()
                            if val and val > 0:
                                rows.append({
                                    
                                    'PC_ID': FCI_d['WH_ID'][i],
                                    'PC_D': dist_name,
                                    'WH_ID': FPS_d['FPS_ID'][j],
                                    'WH_D': dist_name,
                                    'Values': val
                                })

                    df_temp = pd.DataFrame(rows)

                    final_df = pd.concat([final_df, df_temp], ignore_index=True)


                # ================= SAVE OUTPUT =================
                final_df.to_excel('Backend//Tagging_Sheet_Pre_FRice.xlsx', index=False)

                print("✅ LP District-wise tagging completed") 
                
                
                        # ================= POST PROCESSING =================

                # --- 1. WHEAT  ALLOCATION ---
                # Sum allocation per warehouse
                wh_alloc = final_df.groupby('PC_ID')['Values'].sum().reset_index()
                wh_alloc.rename(columns={'Values': 'Used_Allocation'}, inplace=True)
                

                # Merge with original warehouse data
                FCI_updated = pd.merge(FCI, wh_alloc, on='PC_ID', how='left')
                
               

                # Fill NaN with 0 (warehouses not used)
                FCI_updated['Used_Allocation'] = FCI_updated['Used_Allocation'].fillna(0)
                FCI_updated['Remaining_Wheat'] = (FCI_updated['Wheat_Procure'] - FCI_updated['Used_Allocation'])
                
                
                # --- 1. WAREHOUSE USED ALLOCATION ---
                # Sum allocation per warehouse
                wh_alloc1 = final_df.groupby('WB_ID')['Values'].sum().reset_index()
                wh_alloc1.rename(columns={'Values': 'Used_Allocation1'}, inplace=True)
                

                # Merge with original warehouse data
                FCI_updated1 = pd.merge(FCI, wh_alloc1, on='WB_ID', how='left')
                
               

                # Fill NaN with 0 (warehouses not used)
                FCI_updated1['Used_Allocation1'] = FCI_updated1['Used_Allocation1'].fillna(0)
                FCI_updated1['Remaining_Capacity1'] = (FCI_updated1['Available_Capacity'] - FCI_updated['Used_Allocation1'])
                


                
                
                


                # ================= SAVE TO NEW FILE =================
                output_file = 'Backend//Data_3.xlsx'

                with pd.ExcelWriter(output_file, engine='xlsxwriter') as writer:
                    FCI_updated.to_excel(writer, sheet_name='A.1 Warehouse', index=False)
                    FCI_updated1.to_excel(writer, sheet_name='A.2 FPS', index=False)
                    
                    
                df31 = pd.read_excel('Backend//Tagging_Sheet_Pre_FRice.xlsx')     

                usn = pd.ExcelFile('Backend//Data_1.xlsx')
                FCI = pd.read_excel(usn, sheet_name='A.1 Warehouse', index_col=None)
                FPS = pd.read_excel(usn, sheet_name='A.2 FPS', index_col=None)

                df4 = pd.merge(df31, FCI, on='WH_ID', how='inner')
                df4 = df4[[
                    'WH_ID',
                    'WH_Name',
                    'WH_District',
                    'WH_Lat',
                    'WH_Long',
                    'FPS_ID',
                    'Values',
                    ]]
                df4 = pd.merge(df4, FPS, on='FPS_ID', how='inner')
                df51 = df4[[
                    'WH_ID',
                    'WH_Name',
                    'WH_District',
                    'WH_Lat',
                    'WH_Long',
                    'FPS_ID',
                    'FPS_Name',
                    'FPS_District',
                    'FPS_Lat',
                    'FPS_Long',
                    'Values',
                    ]]
                df51.insert(0, 'Scenario', 'Optimized')
                df51.insert(1, 'From', 'MLSP')
                df51.insert(2, 'From_State', 'AndhraPradesh')
                df51.insert(7, 'To', 'FPS')
                df51.insert(8, 'To_State', 'AndhraPradesh')
                df51.insert(9, 'Commodity', 'FRice')
                df51.rename(columns={
                    'WH_ID': 'From_ID',
                    'WH_Name': 'From_Name',
                    'WH_Lat': 'From_Lat',
                    'WH_Long': 'From_Long',
                    }, inplace=True)
                df51.rename(columns={
                    'FPS_ID': 'To_ID',
                    'FPS_Name': 'To_Name',
                    'FPS_Lat': 'To_Lat',
                    'FPS_Long': 'To_Long',
                    'Values' :'Quantity'
                    }, inplace=True)
                df51.rename(columns={'WH_District': 'From_District',
                           'FPS_District': 'To_District'}, inplace=True)
                df51 = df51.loc[:, [
                    'Scenario',
                    'From',
                    'From_State',
                    'From_District',
                    'From_ID',
                    'From_Name',
                    'From_Lat',
                    'From_Long',
                    'To',
                    'To_ID',
                    'To_Name',
                    'To_State',
                    'To_District',
                    'To_Lat',
                    'To_Long',
                    'Commodity',
                    'Quantity',
                    ]]
                    
                def convert_to_numeric(value):
                    try:
                        return pd.to_numeric(value)
                    except ValueError:
                        return value
                        
                
                df51['From_ID'] = df51['From_ID'].apply(convert_to_numeric)
                df51['To_ID'] = df51['To_ID'].apply(convert_to_numeric)
                
               
                df51.to_excel('Backend//Tagging_Sheet_Pre12_FRice.xlsx', sheet_name='BG_FPS',index=False,)


            else:
                pd.DataFrame(columns=columns_18).to_excel('Backend//Tagging_Sheet_Pre12_FRice.xlsx', index=False)

            # ================= SECOND STAGE =================
            
            
            input_file = 'Backend//Data_3.xlsx'
            input1 = pd.ExcelFile(input_file)

            FCI = pd.read_excel(input1, sheet_name='A.1 Warehouse')
            FPS = pd.read_excel(input1, sheet_name='A.2 FPS')

            if FPS['Remaining_Wheat'].sum() > 0:

                node1 = FCI.copy()
                node2 = FPS.copy()

                # ================= DISTANCE MATRIX =================
                dist = [[0 for _ in range(len(node2))] for _ in range(len(node1))]
                R = 6371

                for i in node1.index:
                    for j in node2.index:
                        phi_1 = math.radians(node1["PC_Lat"][i])
                        phi_2 = math.radians(node2["SW_Lat"][j])
                        delta_phi = math.radians(node2["SW_Lat"][j] - node1["SW_Lat"][i])
                        delta_lambda = math.radians(node2["PC_Long"][j] - node1["PC_Long"][i])

                        x = math.sin(delta_phi / 2.0) ** 2 + math.cos(phi_1) * math.cos(phi_2) * math.sin(delta_lambda / 2.0) ** 2
                        y = 2 * math.atan2(math.sqrt(x), math.sqrt(1 - x))
                        dist[i][j] = R * y

                # ================= CLEAN DATA =================
                FCI['Storage_Point_P'] = FCI['Storage_Point_P'].str.replace(' ', '')
                FPS['Storage_Point_W'] = FPS['Storage_Point_W'].str.replace(' ', '')
                
                print("shall1")

                # ================= MODEL =================
                model = LpProblem('Supply-Demand-Problem', LpMinimize)

                # ================= VARIABLES =================
                Variable1 = []
                for i in range(len(FCI)):
                    for j in range(len(FPS)):
                        Variable1.append(f"{FCI['WH_ID'][i]}_{FCI['Storage_Point_P'][i]}_{FPS['FPS_ID'][j]}_{FPS['Storage_Point_W'][j]}_Wheat")

                DV_Variables1 = LpVariable.matrix('X', Variable1, lowBound=0)
                Allocation1 = np.array(DV_Variables1).reshape(len(FCI), len(FPS))

               
                        
                       

                # Objective
                model += lpSum(Allocation1[i][j] * dist[i][j] for i in range(len(FCI)) for j in range(len(FPS)))
                
                Supply = FCI['Remaining_Wheat'].sum
                Demand = FPS['Remaining_Capacity1'].sum
                
                if Supply > Demand:

                    # Demand
                    FPS['Demand'] = FPS['Remaining_Capacity1']
                    
                    for i in range(len(FPS)):
                        model += lpSum(Allocation1[j][i] for j in range(len(FCI))) == FPS['Demand'][i]
                        
                   
                    # Supply
                    for i in range(len(FCI)):
                        model += lpSum(Allocation1[i][j] for j in range(len(FPS))) <= FCI['Remaining_Wheat'][i]
                    
                
                else:
                
                    FPS['Demand'] = FPS['Remaining_Capacity1']
                    
                    for i in range(len(FPS)):
                        model += lpSum(Allocation1[j][i] for j in range(len(FCI))) <= FPS['Demand'][i]
                        
                   
                    # Supply
                    for i in range(len(FCI)):
                        model += lpSum(Allocation1[i][j] for j in range(len(FPS))) == FCI['Remaining_Wheat'][i]
               

                # ================= SOLVE =================
                print("opt_start")
                model.solve(PULP_CBC_CMD(msg=0, gapRel=0.5, timeLimit=900, threads=8))
                print("opt_end")
                
                status = LpStatus[model.status]
                print("Solver Status:", status)
                
                # ✅ Accept feasible solutions
                if status not in ["Optimal", "Feasible"]:
                    print("Optimization failed:", status)

                    data = {
                        "status": 0,
                        "message": f"Solver Status: {status}"
                    }

                    return json.dumps(data, indent=1)

                

                Output_File = open('Backend//Inter_District3.csv', 'w')
                for v in model.variables():
                    if v.value() > 0:
                        Output_File.write(v.name + '\t' + str(v.value()) + '\n')

                Output_File = open('Backend//Inter_District3.csv', 'w')
                for v in model.variables():
                    if v.value() > 0:
                        Output_File.write(v.name + '\t' + str(v.value()) + '\n')                          

                # ================= PROCESS OUTPUT =================
                df9 = pd.read_csv('Backend//Inter_District3.csv', header=None)
                df9.columns = ['Tagging']

                df9[['Var','WH_ID','W_D','FPS_ID','FPS_D','commodity_Value']] = df9['Tagging'].str.split('_', n=5, expand=True)
                df9[['commodity','Values']] = df9['commodity_Value'].str.split('\t', expand=True)
                
               

                df9 = df9[df9['commodity'] != 'Wheat1']
                
                def convert_to_numeric(value):
                    try:
                        return pd.to_numeric(value)
                    except ValueError:
                        return value
                

                df9['WH_ID'] = df9['WH_ID'].apply(convert_to_numeric)
                df9['FPS_ID'] = df9['FPS_ID'].apply(convert_to_numeric)
                
                df9.to_excel('Backend//Tagging_Sheet_Pre_FRice.xlsx', sheet_name='BG_FPS')
                df32 = pd.read_excel('Backend//Tagging_Sheet_Pre_FRice.xlsx')

                
                USN = pd.ExcelFile('Backend//Data_1.xlsx')
                FCI = pd.read_excel(USN, sheet_name='A.1 Warehouse', index_col=None)
                FPS = pd.read_excel(USN, sheet_name='A.2 FPS', index_col=None)

                # ================= MERGE =================
                df41 = pd.merge(df32, FCI, on='WH_ID')
                df41 = pd.merge(df41, FPS, on='FPS_ID')

                df511 = df41[[
                    'WH_ID','WH_Name','WH_District','WH_Lat','WH_Long',
                    'FPS_ID','FPS_Name','FPS_District','FPS_Lat','FPS_Long','Values'
                ]]

                # Add columns
                df511.insert(0, 'Scenario', 'Optimized')
                df511.insert(1, 'From', 'MLSP')
                df511.insert(2, 'From_State', 'AndhraPradesh')
                df511.insert(7, 'To', 'FPS')
                df511.insert(8, 'To_State', 'AndhraPradesh')
                df511.insert(9, 'Commodity', 'FRice')

                # Rename
                df511.rename(columns={
                    'WH_ID':'From_ID','WH_Name':'From_Name','WH_Lat':'From_Lat','WH_Long':'From_Long',
                    'FPS_ID':'To_ID','FPS_Name':'To_Name','FPS_Lat':'To_Lat','FPS_Long':'To_Long',
                    'Values':'Quantity',
                    'WH_District':'From_District','FPS_District':'To_District'
                }, inplace=True)

                df511 = df511[[
                    'Scenario','From','From_State','From_District','From_ID','From_Name','From_Lat','From_Long',
                    'To','To_ID','To_Name','To_State','To_District','To_Lat','To_Long','Commodity','Quantity'
                ]]

                df511 = df511[df511['Quantity'] != 0]

                # Save final
                df511.to_excel('Backend/Tagging_Sheet_Pre13_FRice.xlsx', sheet_name='BG_FPS', index=False)


                

            else:
                pd.DataFrame(columns=columns_18).to_excel('Backend/Tagging_Sheet_Pre13_FRice.xlsx', index=False)

            # ================= FINAL MERGE =================
            df1 = pd.read_excel('Backend/Tagging_Sheet_Pre12_FRice.xlsx')
            df2 = pd.read_excel('Backend/Tagging_Sheet_Pre13_FRice.xlsx')

            df_combined = pd.concat([df1, df2], ignore_index=True)
            df_combined.to_excel('Backend/Tagging_Sheet_Pre11_FRice.xlsx', index=False)

        # =========================================================
        # ================= MAIN ELSE (NO DEMAND) ==================
        # =========================================================
        else:
            print("⚠️ Allocation_Wheat sum is 0 → skipping full run")

            columns_18 = [
                'Scenario','From','From_State','From_District','From_ID','From_Name','From_Lat','From_Long',
                'To','To_ID','To_Name','To_State','To_District','To_Lat','To_Long','Commodity','Quantity'
            ]

            empty_df = pd.DataFrame(columns=columns_18)
            empty_df.to_excel('Backend/Tagging_Sheet_Pre11_FRice.xlsx', index=False)

            print("✅ Empty final file created") 
            
        
        input = pd.ExcelFile('Backend/Data_1.xlsx')
        node1 = pd.read_excel(input,sheet_name="A.1 Warehouse")
        node1["concatenate"]= node1['WH_Lat'].round(3).astype(str) + ',' + node1['WH_Long'].round(3).astype(str)

        node2 = pd.read_excel(input,sheet_name="A.2 FPS")
        node2["concatenate1"]= node2['FPS_Lat'].round(3).astype(str) + ',' + node2['FPS_Long'].round(3).astype(str)

        DistanceBing = read_protected_excel('Backend//Distance_Initial_L2.xlsx', 'distf', sheet_name='BG_BG')
        Warehouse = read_protected_excel('Backend//Distance_Initial_L2.xlsx', 'distf', sheet_name='Warehouse')
        FPS = read_protected_excel('Backend//Distance_Initial_L2.xlsx', 'distf', sheet_name='FPS')


        Warehouse['Lat_Long_r'] = (
            Warehouse['Lat_Long']
            .str.split(',', expand=True)
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )

        FPS['Lat_Long_r'] = (
            FPS['Lat_Long']
            .str.split(',', expand=True)
            .astype(float)
            .round(3)
            .astype(str)
            .agg(','.join, axis=1)
        )


        node1 = node1[['WH_ID', 'WH_Lat', 'WH_Long','concatenate']]
        War = pd.merge(node1, Warehouse, on='WH_ID')
        df1_w = War[War['concatenate'] != War['Lat_Long_r']]
        Warehouse_ID = df1_w['WH_ID'].unique()



        node2 = node2[['FPS_ID', 'FPS_Lat', 'FPS_Long','concatenate1']]
        FPS1 = pd.merge(node2, FPS, on='FPS_ID')
        df1_f = FPS1[FPS1['concatenate1'] != FPS1['Lat_Long_r']]
        FPS_ID = df1_f['FPS_ID'].unique()
        
        BG_BG = DistanceBing
        Distance1 = BG_BG.drop(columns=BG_BG.columns[BG_BG.columns.isin(Warehouse_ID)])
        Distance2 =Distance1.T
        Distance3 = Distance2.drop(columns=Distance2.columns[Distance2.columns.isin(FPS_ID)])
        Distance3 = Distance3.T
        with pd.ExcelWriter('Backend//AP_Distance_L2.xlsx') as writer:
            Distance3.to_excel(writer, sheet_name='BG_BG',index=False)
            
        
        Cost = pd.ExcelFile("Backend//AP_Distance_L2.xlsx")
        BG_BG = pd.read_excel(Cost,sheet_name="BG_BG")
        Cost.close()
        
        data1 = pd.ExcelFile("Backend//Tagging_Sheet_Pre11.xlsx")
        df5 = pd.read_excel(data1,sheet_name="BG_FPS1")
        data1.close()
        print("shallu")

        Distance_BG_BG = {}
        column_list_BG_BG = list(BG_BG.columns.astype(str))
        row_list_BG_BG = list(BG_BG.iloc[:, 0].astype(str))

        for ind in df5.index:
            from_code = df5['From_ID'][ind]
            to_code = df5['To_ID'][ind]
            from_code_str = str(from_code)
            to_code_str = str(to_code)
            
            if to_code_str in row_list_BG_BG and from_code_str in column_list_BG_BG:
                index_i = row_list_BG_BG.index(to_code_str)
                index_j = column_list_BG_BG.index(from_code_str)
                key = to_code_str + "_" + from_code_str
                Distance_BG_BG[key] = BG_BG.iloc[index_i, index_j] 
                
                
        df5["Tagging"] = df5['To_ID'].astype(str) + '_' + df5['From_ID'].astype(str)
        df5['Distance'] = df5['Tagging'].map(Distance_BG_BG).astype(object)
        df5.fillna('shallu', inplace=True)
        df5.to_excel('Backend//Result_Sheet12.xlsx', sheet_name='Warehouse_FPS', index=False)        
        
        
        
# ----------------------------------------------------------------------------------------------------------------------------------------------
# -------------------- READ INPUT --------------------
        
        # ----------------------------------------------------------------------------------------------------------------------------------------------
# -------------------- READ INPUT --------------------
        

       

        # -------------------- LOAD DATA --------------------
        Result_Sheet1 = pd.ExcelFile("Backend//Result_Sheet12.xlsx")
        df6 = pd.read_excel(Result_Sheet1, sheet_name="Warehouse_FPS")
        
        Result_Sheet1.close()

        # -------------------- FILTER SHALLU --------------------
        df7 = df6.loc[df6['Distance'] == "shallu"].reset_index(drop=True)
        
                

        # Drop Tagging column (safe)
        df6.drop(columns=['Tagging'], errors='ignore', inplace=True)

        # Common column structure
        columns = [
            'Scenario','From','From_State','From_District','From_ID','From_Name',
            'From_Lat','From_Long','To','To_ID','To_Name','To_State','To_District',
            'To_Lat','To_Long','commodity','quantity','Distance'
        ]
        
        

        # ============================
        # ✅ CASE 1: NO "shallu"
        # ============================
        if df7.empty:
            print("No 'shallu' found → skipping API")

            df10 = df6.copy()
            df10 = df10[columns]

        # ============================
        # ✅ CASE 2: PROCESS API
        # ============================
        else:
            print(f"'shallu' found → processing {len(df7)} rows")
            print("anmol")

            # -------------------- API DETAILS --------------------
            auth_url = 'https://staging2.pmgatishakti.gov.in/DFPD/authenticate'
            distance_url = 'https://staging2.pmgatishakti.gov.in/PMGatishaktiApiService/dfpdapi/roaddistance'

            auth_payload = {
                "username": "DFPD_C",
                "password": "W9Vtb8WKkt3"
            }

            FILE_PATH = 'distanceIndent.json'

            # -------------------- GET TOKEN --------------------
            def get_token():
                try:
                    response = requests.post(auth_url, json=auth_payload, timeout=10)
                    if response.status_code == 200:
                        return response.json().get('token')
                    return None
                except requests.exceptions.RequestException as e:
                    print("Auth API Error:", e)
                    raise Exception("PMGatiShakti Authentication Service is currently unavailable. Please check your internet connection or try again later.")

            # -------------------- BATCH API --------------------
            def process_batch(df_batch, token):
                headers = {'Authorization': f'Bearer {token}'}

                data = {
                    "parameter": [{
                        "src_lng": row["From_Long"],
                        "src_lat": row["From_Lat"],
                        "dest_lng": row["To_Long"],
                        "dest_lat": row["To_Lat"]
                    } for _, row in df_batch.iterrows()]
                }

                try:
                    with open(FILE_PATH, 'w') as f:
                        json.dump(data, f, indent=4)

                    with open(FILE_PATH, 'rb') as f:
                        files = {'LatsLongsFile': f}
                        response = requests.post(distance_url, headers=headers, files=files, timeout=30)

                    return response

                except requests.exceptions.RequestException as e:
                    print("Batch API Error:", e)
                    raise Exception("PMGatiShakti Distance Service is currently unavailable. Please check your internet connection or try again later.")
                except Exception as e:
                    print("Batch API Error:", e)
                    return None

            # -------------------- SINGLE ROW API --------------------
            def process_single_row(row, token):
                headers = {'Authorization': f'Bearer {token}'}

                data = {
                    "parameter": [{
                        "src_lng": row["From_Long"],
                        "src_lat": row["From_Lat"],
                        "dest_lng": row["To_Long"],
                        "dest_lat": row["To_Lat"]
                    }]
                }

                try:
                    with open(FILE_PATH, 'w') as f:
                        json.dump(data, f, indent=4)

                    with open(FILE_PATH, 'rb') as f:
                        files = {'LatsLongsFile': f}
                        response = requests.post(distance_url, headers=headers, files=files, timeout=15)

                    if response.status_code != 200:
                        return 0

                    res_json = response.json()
                    api_data = res_json.get("data", [])

                    if len(api_data) == 0:
                        return 0

                    distance = api_data[0].get("distance")

                    if isinstance(distance, (int, float)):
                        return distance

                    return 0

                except requests.exceptions.RequestException as e:
                    print("Row API Error:", e)
                    raise Exception("PMGatiShakti Distance Service is currently unavailable. Please check your internet connection or try again later.")
                except Exception as e:
                    print("Row API Error:", e)
                    return 0

            # -------------------- MAIN PROCESS --------------------
            batch_size = 1000
            total_rows = len(df7)
            num_batches = (total_rows + batch_size - 1) // batch_size

            dist3 = []

            for batch_num in range(num_batches):
                print(f"Processing batch {batch_num+1}/{num_batches}")

                start_idx = batch_num * batch_size
                end_idx = min((batch_num + 1) * batch_size, total_rows)
                df_batch = df7.iloc[start_idx:end_idx]

                token = get_token()
                if not token:
                    print("Token failed → filling with 0")
                    dist3.extend([0] * len(df_batch))
                    continue

                response = process_batch(df_batch, token)

                fallback_required = False

                if not response or response.status_code != 200:
                    fallback_required = True
                else:
                    try:
                        response_json = response.json()
                        api_data = response_json.get("data", [])

                        if len(api_data) != len(df_batch):
                            fallback_required = True
                        else:
                            for row_data in api_data:
                                if not isinstance(row_data.get("distance"), (int, float)):
                                    fallback_required = True
                                    break
                    except:
                        fallback_required = True

                # ---------------- FALLBACK ----------------
                if fallback_required:
                    print(f"Batch {batch_num+1} failed → row-wise fallback")

                    for _, row in df_batch.iterrows():
                        distance = process_single_row(row, token)

                        if distance == 0:
                            print(f"0 distance → {row['From_ID']} to {row['To_ID']}")

                        dist3.append(distance)

                # ---------------- NORMAL ----------------
                else:
                    for row_data in api_data:
                        dist3.append(row_data.get("distance"))


            df7["Distance"] = dist3
            print("Pawaa")

            # Merge with non-shallu
            df9 = df6.loc[df6['Distance'] != "shallu"]

            df9 = df9[columns]
            df7 = df7[columns]

            df10 = pd.concat([df9, df7], ignore_index=True)

        # ============================
        # FINAL OUTPUT (COMMON)
        # ============================
        result = (df10['quantity'] * df10['Distance']).sum()

        print("Total Result:", result)

        df10.to_excel('Backend//Result_Sheet_leg1.xlsx', sheet_name='Warehouse_FPS', index=False)

        print("Process Completed Successfully")
# ----------------------------------------------------------------------------------------------------------------------------------------------        

        
        
        
# ----------------------------------------------------------------------------------------------------------------------------------------------        

            
        data= {}        
            


        
       # ---------------------------------------------------------------------------------------------------------------------------------------------
        dfinal = pd.read_excel('Backend//Result_Sheet_leg1.xlsx', sheet_name='Warehouse_FPS')
                     
        Total_Demand=  (dfinal['quantity'])            
        
        data["Scenario"]="Inter"
        data["Scenario_Baseline"] = "Baseline"
        
        data["WH_Used"] = dfinal['From_ID'].nunique()
        data["WH_Used_Baseline"] = "5"
        
        data["FPS_Used"] = dfinal['To_ID'].nunique()
        data["FPS_Used_Baseline"] = "76"
        
        data['Demand'] = (dfinal['quantity']).sum()
        data['Demand_Baseline'] = "69,247"
        result1 = ((dfinal['quantity']) * dfinal['Distance']).sum()
        Total_Demand= (df10['quantity']).sum()
        
        data['Total_QKM'] = float(result1)
        data['Total_QKM_Baseline'] = "67,16,263"
        
        data['Average_Distance'] = float(round(result1, 2)) / Total_Demand
        data['Average_Distance_Baseline'] = "96.66"

        if stop_process==True:
            data = {}
            data['status'] = 0
            data['message'] = "Process Stopped"
            json_data = json.dumps(data)
            json_object = json.loads(json_data)
            return json.dumps(json_object, indent=1)                     
        
        save_to_database_leg1(month, year, day)
        save_monthly_data_leg1(month, year, day, float(result))
        
        json_data = json.dumps(data)
        json_object = json.loads(json_data)

        if os.path.exists('ouputPickle.pkl'):
            os.remove('ouputPickle.pkl')

        # open pickle file
        dbfile1 = open('ouputPickle.pkl', 'ab')

    # save pickle data
    pickle.dump(json_object, dbfile1)
    dbfile1.close()
    data['status'] = 1
    json_data = json.dumps(data)
    json_object = json.loads(json_data)
    return json.dumps(json_object, indent=1)
    


if __name__ == "__main__":
    app.run(host='0.0.0.0', port=5000, debug=True)
# -*- coding: utf-8 -*-

#!/usr/bin/python
# -*- coding: utf-8 -*-
