<?php
require('util/Connection.php');
require('util/SessionCheck.php');
require('Header.php');
// require('header2.php');
?>

<head>
	<link href='https://fonts.googleapis.com/css?family=Roboto+Condensed:400,300' rel='stylesheet' type='text/css'>

</head>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
	#processingPopup {
		display: none;
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background-color: rgba(255, 255, 255, 0.8);
		align-items: center;
		justify-content: center;
		z-index: 9999;
	}

	#processingPopup .spinner {
		border: 6px solid #3498db;
		border-top: 6px solid #f39c12;
		border-radius: 50%;
		width: 40px;
		height: 40px;
		animation: spin 1s linear infinite;
	}

	#optimisedtable {
		border-collapse: collapse;
		width: 100%;
		margin-top: 0px;
	}

	#optimisedtable th,
	#optimisedtable td {
		border: 1px solid #ddd;
		padding: 8px;
		text-align: center;
	}

	#optimisedtable th {
		background-color: #5E35B1;
		color: white;
	}

	#optimisedtable tbody tr:nth-child(even) {
		background-color: #f2f2f2;
	}

	#optimisedtable tbody tr:hover {
		background-color: #ddd;
	}

	.help-block b {
		font-weight: bold;
	}

	*,
	*:before,
	*:after {
		box-sizing: border-box;
	}

	/* html {
	  font-family: 'Roboto Condensed', sans-serif;
	  display: flex;
	  justify-content: center;
	  align-items: center;
	  text-align: center;
	  height: 100%;
	  color: #ECEFF1;
	  background-image: radial-gradient(lighten(#263238, 20%), #263238);
	} */

	.toggle {
		position: relative;
		display: block;
		margin: 0 auto;
		width: 140px;
		height: 40px;
		color: black;
		outline: 0;
		text-decoration: none;
		border-radius: 60px;
		border: 2px solid #546E7A;
		background-color: white;
		transition: all 500ms;
		cursor: pointer;
		/* Added cursor style */
	}

	.toggle:active {
		background-color: darken(red, 5%);
	}

	.toggle:hover:not(.toggle--moving):after {
		background-color: green;
	}

	.toggle:after {
		content: attr(data-content);
		/* Use content attribute to display On/Off */
		display: block;
		position: absolute;
		top: 0px;
		bottom: 1px;
		left: 1px;
		width: calc(50% - 4px);
		line-height: 52px;
		/* Adjust line-height for vertical centering */
		text-align: center;
		text-transform: uppercase;
		font-size: 20px;
		color: white;
		background-color: red;
		border: 2px solid;
		transition: all 500ms;
		border-radius: 50px;
	}

	.toggle--on:after {
		transform: translate(100%, 0);
		color: whitesmoke;
		background-color: green;
	}

	.toggle--off:after {
		color: whitesmoke;
		background-color: red;
	}

	.toggle--moving {
		background-color: darken(#263238, 5%);
	}

	.toggle--moving:after {
		color: transparent;
		border-color: darken(#546E7A, 8%);
		background-color: darken(white, 10%);
		transition: color 0s, transform 500ms, border-radius 500ms, background-color 500ms;
	}

	/* h1 {
	  font-size: 34px;
	  margin-top: 0;
	  margin-bottom: -12px;
	} */

	@keyframes spin {
		0% {
			transform: rotate(0deg);
		}

		100% {
			transform: rotate(360deg);
		}
	}

	.btn {
		border-radius: 20px;
		/* Set border radius */
		/* Add other button styles as needed */
	}

	.upload_button_class {
		background-color: #F2F3F5;
		border-radius: 30px;
		box-shadow: -10px -10px 15px 0 #f6f6f6, 10px 10px 15px 0 #cecece;
		color: #676767;
		height: 40px;
		margin: auto;
		padding: 0;
		/* Adjust padding as needed */
		text-align: center;
		transition: all .2s ease;
		width: 276px;
		border: none;
		/* Remove border for this button */
		cursor: pointer;
		/* Add cursor pointer to indicate interactivity */
		display: flex;
		/* Allow flexible layout */
		justify-content: center;
		/* Center content horizontally */
		align-items: center;
		/* Center content vertically */
		margin-left: 481px;

	}

	.panel-footer {
		padding: 0;
		/* Remove padding for the entire panel-footer */
	}

	.panel-footer .btn {
		padding: 2px 10px;
		/* Apply padding to the button */
	}
	#optimisedtable th {
    text-align: center; /* Center-align the text within table headers */
}
*{
      box-sizing: border-box;
    }

    button {
      outline: none;
      cursor: pointer;
    }

    .icon {
      display: inline-block;
      width: 1em;
      height: 1em;
      fill: currentColor;
    }

    body {
      font-family: 'Open Sans', sans-serif;
      font-size: 16px;
      color: #fff;
      background: linear-gradient(to right, #566a39 0%, #75986f 100%);
    }

	.button-wrapper {
  position: relative;
  display: inline-block;
  padding: 2px 3px; /* Adjust padding */
  min-width: 10px; /* Adjust minimum width */
  min-height: 40px; /* Adjust minimum height */
  border-radius: 15px; /* Adjust border-radius */
  box-shadow: 0px -1px 1px rgba(255, 255, 255, 0.22), inset 0px -1px 3px rgba(0, 0, 0, 0.2);
}

.button {
  position: relative;
  height: 40px; /* Adjust height */
  min-width: 5px; /* Adjust minimum width */
  padding: 0 5px; /* Adjust padding */
  border-radius: 15px; /* Adjust border-radius */
  background: #ff005a;
  background: linear-gradient(#ff4184 0%, #ff005a 100%, #ff005a);
  border: none;
  font-size: 10px; /* Adjust font size */
  color: white;
  line-height: 40px; /* Adjust line height */
  font-weight: 700;
}


    .button__text {
      position: relative;
      display: block;
      height: 114px;
      white-space: nowrap;
      opacity: 1;
    }

    .button__text--download {
      width: 150px;
      transition: opacity 0.5s ease, width 0.5s ease;

      &.is_animated {
        overflow: hidden;
        width: 0px;
        opacity: 0;
      }
    }

    .button__text--progress {
      margin-right: -35px;
      margin-left: -35px;
      width: 114px;
      font-size: 40px;
      opacity: 0;
      transition: opacity 0.5s ease;

      sub {
        font-size: .5em;
        font-weight: normal;
      }

      &.is_animated {
        opacity: 1;
      }
    }

    .button__text--complete {
      position: absolute;
      top: 0;
      left: 0;
      z-index: 999;
      border-radius: 50%;
      height: 114px;
      width: 114px;
      box-shadow: inset 0px -1px 6px 0px rgba(255, 255, 255, 0.73);
      background: #3acaff;
      transform: scale(1.5);
      transition: transform 0.5s ease;

      &.is_animated {
        transform: scale(1);
      }
    }

    .button__icon--cloud-download,
    .button__icon--checkmark {
      position: relative;
      top: 7px;
    }

    .pie-loader {
      position: absolute;
      top: 0;
      left: 0;
      z-index: -1;
      width: 160px;
      height: 160px;
      opacity: 1;
      transition: opacity 0.1s ease;

      svg {
        width: 100%;
        height: 100%;
      }

      circle {
        fill: #3acaff;
        stroke: #3acaff;
        stroke-width: 80px;
        stroke-dasharray: 0 252;
        transition: all 0.1s linear;
      }

      &.is_hidden {
        opacity: 0;
      } 
    }
.btn btn-success pull-right{
	
}
</style>

<!-- START BREADCRUMB -->
<ul class="breadcrumb">
	<li><a href="#">Home</a></li>
	<li class="active">Punjab Intra Route Optimization For PDS</li>
</ul>
<!-- END BREADCRUMB -->
<div>
	
</div>

<!-- PAGE CONTENT WRAPPER -->
<div class="page-content-wrap"
	style="background-image: url('img/1 (2).png'); background-repeat: no-repeat; background-size: cover;">

	<div class="row">
		<div class="col-md-12">

			<!-- START SIMPLE DATATABLE -->
			<div class="panel panel-default">
				<div class="panel-heading" style="text-align: center;">
					<h1 style="font-weight: bold; color: #335566;">Punjab Intra Route Optimization For PDS</h1>

				</div>
			</div>



			<div class="row">
					<div class="panel-body">

						<div id="processingPopup">
							<div class="spinner"></div>
						</div>
						&nbsp
						<div class="row">
							<div class="col-md-3 mb-4">
								<div class="card h-100"
									style="background-color:#56A5FF; color:white; padding:15px; font-weight: bold;">
									<div style="font-size:20px" id="total_warehouse">DEPOT </div>
									<div style="font-size:15px">Total Warehouse</div>
								</div>
							</div>
							<div class="col-md-3 mb-4">
								<div class="card h-100"
									style="background-color:#3FDBBC; color:white; padding:15px; font-weight: bold;">
									<div style="font-size:20px" id="total_supply"> Qt</div>
									<div style="font-size:15px">Total Capacity</div>
								</div>
							</div>
							<div class="col-md-3 mb-4">
								<div class="card h-100"
									style="background-color:#FFC167; color:white; padding:15px; font-weight: bold;">
									<div style="font-size:20px" id="total_fps">FPS </div>
									<div style="font-size:15px">Total FPS Counts</div>
								</div>
							</div>
							<div class="col-md-3 mb-4">
								<div class="card h-100"
									style="background-color:#F96981; color:white; padding:15px; font-weight: bold;">
									<div style="font-size:20px" id="total_demand">0 Qt</div>
									<div style="font-size:15px">Total FPS Demands</div>
								</div>
							</div>
						</div>
					</div>
					&nbsp
					</br></br></br>
					<center>
						<div style="width:80%"><canvas id="myChart" width="400" height="200"></canvas></div>
					</center>
					
					</br></br></br>
							<div class="row">
								<div class="col-md-4">
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label class="col-md-5 ontrol-label" style="color:#000">Select Month</label>
										<div class="col-md-7">  
											<div class="input-group">
											<span class="input-group-addon"><span class="fa fa-certificate"></span></span>						
											<select class="form-control" id="month" name="month" onchange="fetchDataFromServer()">
												<option value='jan'>January</option>
												<option value='feb'>February</option>
												<option value='march'>March</option>
												<option value='april'>April</option>
												<option value='may'>May</option>
												<option value='june'>June</option>
												<option value='july'>July</option>
												<option value='aug'>August</option>
												<option value='sept'>September</option>
												<option value='oct'>October</option>
												<option value='nov'>November</option>
												<option value='dec'>December</option>
											</select>
											</div>
										</div>
									</div>
								</div>
								<div class="col-md-4">
								</div>
							</div>
							</br></br>
								<center>
									<button id="downloadCSV" class="btn btn-warning" style="margin-bottom: 10px;" type="button">Download CSV</button>
									<button id="downloadXLSX" class="btn btn-success" style="margin-bottom: 10px;" type="button">Download XLSX</button>
									<button id="downloadPDF" class="btn btn-danger" style="margin-bottom: 10px;" type="button">Download PDF</button>
								</center>
			</div>
			&nbsp
			</br></br></br>
				
				
			</div>

			<!-- END SIMPLE DATATABLE -->

		</div>
	</div>

</div>
<!-- PAGE CONTENT WRAPPER -->
</div>
<!-- END PAGE CONTENT -->
</div>
<!-- END PAGE CONTAINER -->



<!-- START SCRIPTS -->
<!-- START PLUGINS -->
<script type="text/javascript" src="js/plugins/jquery/jquery.min.js"></script>
<script type="text/javascript" src="js/plugins/jquery/jquery-ui.min.js"></script>
<script type="text/javascript" src="js/plugins/bootstrap/bootstrap.min.js"></script>
<!-- END PLUGINS -->

<!-- THIS PAGE PLUGINS -->
<script type='text/javascript' src='js/plugins/icheck/icheck.min.js'></script>
<script type="text/javascript" src="js/plugins/mcustomscrollbar/jquery.mCustomScrollbar.min.js"></script>
<script type="text/javascript" src="js/plugins/datatables/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="js/plugins/tableexport/tableExport.js"></script>
<script type="text/javascript" src="js/plugins/tableexport/jquery.base64.js"></script>
<script type="text/javascript" src="js/plugins/tableexport/html2canvas.js"></script>
<script type="text/javascript" src="js/plugins/tableexport/jspdf/libs/sprintf.js"></script>
<script type="text/javascript" src="js/plugins/tableexport/jspdf/jspdf.js"></script>
<script type="text/javascript" src="js/plugins/tableexport/jspdf/libs/base64.js"></script>

<script type="text/javascript" src="js/plugins.js"></script>
<script type="text/javascript" src="js/actions.js"></script>
<!-- END PAGE PLUGINS -->

<!-- START TEMPLATE -->

<!-- END TEMPLATE -->

<script>
	function post(params, file) {

		method = "post";
		path = file;

		var form = document.createElement("form");
		form.setAttribute("method", method);
		form.setAttribute("action", path);

		for (var key in params) {
			if (params.hasOwnProperty(key)) {
				var hiddenField = document.createElement("input");
				hiddenField.setAttribute("type", "hidden");
				hiddenField.setAttribute("name", key);
				hiddenField.setAttribute("value", params[key]);
				form.appendChild(hiddenField);
			}
		}

		document.body.appendChild(form);
		form.submit();
	}


	// Initial data for the chart
	var initialData = {
		labels: ['Jan', 'Feb'],
		datasets: [{
			label: '2024',
			backgroundColor: '#1640D6',
			data: [12, 18]
		}]
	};

	// Get the canvas element
	var ctx = document.getElementById('myChart').getContext('2d');

	// Create a bar chart with initial data
	var myChart = new Chart(ctx, {
		type: 'bar',
		data: initialData,
		options: {
			scales: {
				y: {
					beginAtZero: true
				}
			}
		}
	});
	
	
	// Function to generate a random color
	function getRandomColor() {
		var letters = '0123456789ABCDEF';
		var color = '#';
		for (var i = 0; i < 6; i++) {
			color += letters[Math.floor(Math.random() * 16)];
		}
		return color;
	}
	
	function fetchMonthlyData(){
		const formData = new FormData();

		fetch('http://localhost:5000/readMonthlyData', {
			method: 'POST',
			body: formData
		})
		.then(response => response.json())
		.then(data => {
			if(data["status"]==1){
				jsonData = data["data"];				
				var dataset = [];
				var labels = [];
				var year = "";
				jsonData.forEach(function(entry) {
					year = entry.year;
					labels.push(entry.month);
					dataset.push(entry.data);
				});
				
				
				var newData = {
				labels: labels,
				datasets: [{
						label: year,
						backgroundColor: '#5383FF',
						data: dataset
					}]
				};
				
				myChart.data = newData;
				myChart.update();
			}
		})
		.catch(error => {
			console.error('Error:', error);
		});
	}
	fetchMonthlyData();
	
	function fetchFromDb(){
		
		const formData = new FormData();

		fetch('http://localhost:5000/getfcidata', {
			method: 'POST',
			body: formData
		})
			.then(response => response.json())
			.then(data => {
				console.log('Response from server:', data);
				document.getElementById("total_warehouse").innerHTML = "FCI " + data["Warehouse_No"];
				document.getElementById("total_demand").innerHTML = data["Total_Demand"] + " Qt";
				document.getElementById("total_fps").innerHTML = "FPS " + data["FPS_No"];
				document.getElementById("total_supply").innerHTML = data["Total_Supply"] + " Qt";
			})
			.catch(error => {
				console.error('Error:', error);
			});
	}
	fetchFromDb();


	document.getElementById('downloadCSV').addEventListener('click', async function() {
		try {
			var month = document.getElementById("month").value;
			const csvResponse = await fetch('api/DownloadOptimalData.php?format=csv&month='+month);
			const csvBlob = await csvResponse.blob();
			downloadFile(csvBlob, 'Downloaded_CSV.csv');
		} catch (error) {
			console.error('Error downloading CSV file:', error);
		}
	});

	// Event listener for downloading XLSX
	document.getElementById('downloadXLSX').addEventListener('click', async function() {
		try {
			var month = document.getElementById("month").value;
			const excelResponse = await fetch('api/DownloadOptimalData.php?format=xlsx&month='+month);
			const excelBlob = await excelResponse.blob();
			downloadFile(excelBlob, 'Downloaded_Excel.xlsx');
		} catch (error) {
			console.error('Error downloading XLSX file:', error);
		}
	});

	// Event listener for downloading PDF
	document.getElementById('downloadPDF').addEventListener('click', async function() {
		try {
			var month = document.getElementById("month").value;			
			const pdfResponse = await fetch('api/DownloadOptimalData.php?format=pdf&month='+month);
			const pdfBlob = await pdfResponse.blob();

			const url = window.URL.createObjectURL(pdfBlob);
			const link = document.createElement('a');
			link.href = url;
			link.download = 'Downloaded_PDF.pdf';
			link.click();
			window.URL.revokeObjectURL(url);
		} catch (error) {
			console.error('Error downloading PDF file:', error);
		}
	});



	// Functions for file download and PDF generation (similar to previous code)
	function downloadFile(blob, fileName) {
		const url = window.URL.createObjectURL(blob);
		const link = document.createElement('a');
		link.href = url;
		link.download = fileName;
		link.click();
		window.URL.revokeObjectURL(url);
	}


</script>
</body>

</html>