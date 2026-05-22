<?php
require('util/Connection.php');
require('util/SessionCheck.php');
require('Header.php');

$district = ucfirst($_SESSION['district_district']);

$district = "";
$Name = "";
$ID = "";
$Storage_Point = "";
$Capacity = "";
$Latitude = "";
$Longitude = "";
$uniqueid = "";
$active = "";

if(isset($_POST["uid"])){
	$uid = $_POST["uid"];
	$query = "SELECT * FROM weighbridge WHERE uniqueid='$uid'";
	$result = mysqli_query($con,$query);
	$numrows = mysqli_num_rows($result);
	if($numrows!=0){
		$row = mysqli_fetch_assoc($result);
		$district = $row['District'];
		$Name = $row['Name'];
		$ID = $row['ID'];
		$Storage_Point = $row['Storage_Point'];
		$Capacity = $row['Capacity'];
		$Latitude = $row['Latitude'];
		$Longitude = $row['Longitude'];
		$uniqueid = $row['uniqueid'];
		$active = $row['active'];
	}
	else{
		header("Location:Weighbridge.php");
	}
}
else{
	header("Location:Weighbridge.php");
}

?>

<script src="crypto-js/crypto-js.js"></script>
<script src="js/Encryption.js"></script>

<script>
	function verifyCaptcha() {
		var readableString = document.getElementById("password").value;
		var nonceValue = "nonce_value";
		let encryption = new Encryption();
		var encrypted = encryption.encrypt(readableString, nonceValue);
		document.getElementById("password").value = encrypted;
	}
</script>

                <!-- START BREADCRUMB -->
                <ul class="breadcrumb">
                    <li><a href="Weighbridge.php">Home</a></li>
                    <li class="active">Weighbridge Edit</li>
                </ul>
                <!-- END BREADCRUMB -->


				<!-- PAGE CONTENT WRAPPER -->
                 <div class="page-content-wrap">

                    <div class="row">
                        <div class="col-md-12">

                            <form action="api/WeighbridgeEdit.php" method="POST" class="form-horizontal" enctype = "multipart/form-data">
                            <div class="panel panel-default">
                               <div class="panel-body">
                                    <p>Fill this form to edit weighbridge.</p>
                                </div>

                             <div class="panel-body">

                                    <div class="row">

                                        <div class="col-md-6">

											<div class="form-group">
                                                <label class="col-md-3 control-label">District*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="district" name="district" value="<?php echo $district; ?>" readonly />
                                                    </div>
                                                    <span class="help-block">District</span>
                                                </div>
                                            </div>

											<div class="form-group">
                                                <label class="col-md-3 control-label">Name*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="Name" name="Name" value="<?php echo $Name; ?>" required />
                                                    </div>
                                                    <span class="help-block">Weighbridge Name</span>
                                                </div>
                                            </div>

											<div class="form-group">
                                                <label class="col-md-3 control-label">Storage Point*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="Storage_Point" name="Storage_Point" value="<?php echo $Storage_Point; ?>" required />
                                                    </div>
                                                    <span class="help-block">Storage Point</span>
                                                </div>
                                            </div>

											<div class="form-group">
                                                <label class="col-md-3 control-label">Capacity*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="Capacity" name="Capacity" value="<?php echo $Capacity; ?>" required />
                                                    </div>
                                                    <span class="help-block">Capacity</span>
                                                </div>
                                            </div>

											<div class="form-group">
                                                <label class="col-md-3 control-label">Latitude*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="Latitude" name="Latitude" value="<?php echo $Latitude; ?>" required />
                                                    </div>
                                                    <span class="help-block">Latitude</span>
                                                </div>
                                            </div>

                                        </div>

                                        <div class="col-md-6">

											<div class="form-group">
                                                <label class="col-md-3 control-label">Weighbridge ID*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="ID" name="ID" value="<?php echo $ID; ?>" required />
                                                    </div>
                                                    <span class="help-block">Weighbridge ID</span>
                                                </div>
                                            </div>

											<div class="form-group">
                                                <label class="col-md-3 control-label">Longitude*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="Longitude" name="Longitude" value="<?php echo $Longitude; ?>" required />
                                                    </div>
                                                    <span class="help-block">Longitude</span>
                                                </div>
                                            </div>

                                        </div>

                                    </div>

                                </div>
								<input type="hidden" id="uniqueid" name="uniqueid" value="<?php echo $uniqueid; ?>" />
								<input type="hidden" id="active" name="active" value="<?php echo $active; ?>" />
                                <div class="panel-footer">
                                    <button class="btn btn-primary pull-right" onclick="showPopup()" type="button">Submit</button>
                                </div>
								<div id="popup" class="popup">
										<a class="close" onclick="hidePopup()" style="font-size:25px">×</a>
										</br></br>
										
										<div class="col-md-6">
										
											<div class="form-group">
                                                <label class="col-md-3 control-label">Username*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="username" name="username" required />
                                                    </div>
                                                    <span class="help-block">Username</span>
                                                </div>
                                            </div>
											
											
                                        </div>
                                        <div class="col-md-6">
										
										
											<div class="form-group">
                                                <label class="col-md-3 control-label">Password*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="password" class="form-control" id="password" name="password" required />
                                                    </div>
                                                    <span class="help-block">Password</span>
                                                </div>
                                            </div>
											
											
                                        </div>
										
										<center><button class="btn btn-primary" onclick="verifyCaptcha()">Verify</button></center>
								</div>
                            </div>
                            </form>

                        </div>
                    </div>
					</br></br></br></br></br></br></br></br></br></br></br></br></br></br></br></br></br></br></br>
                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>



    <!-- START SCRIPTS -->
        <script type="text/javascript" src="js/plugins/jquery/jquery.min.js"></script>
        <script type="text/javascript" src="js/plugins/jquery/jquery-ui.min.js"></script>
        <script type="text/javascript" src="js/plugins/bootstrap/bootstrap.min.js"></script>

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
		
		
		<script>
		function showPopup() {
            
			var district = document.getElementById('district').value;
			var Name = document.getElementById('Name').value;
			var Storage_Point = document.getElementById('Storage_Point').value;
			var Capacity = document.getElementById('Capacity').value;
			var Latitude = document.getElementById('Latitude').value;
			var ID = document.getElementById('ID').value;
			var Longitude = document.getElementById('Longitude').value;

            if (district === '' || Name === '' || Storage_Point === '' || Capacity === '' || Latitude === '' || ID === '' || Longitude === '') {
                alert('Please enter all fields');
                return false;
            }
			
            document.getElementById('popup').style.display = 'block';
        }
		
		function hidePopup() {
            document.getElementById('popup').style.display = 'none';
        }
		
		</script>		

    </body>
</html>
