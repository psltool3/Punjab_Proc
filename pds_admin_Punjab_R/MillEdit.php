<?php
require('util/Connection.php');
require('util/SessionCheck.php');
require('Header.php');

$district = "";
$name = "";
$mill_id = "";
$milling_center = "";
$milling_process = "";
$latitude = "";
$longitude = "";
$active = "";

if(isset($_POST["uid"])){
	$uniqueid = $_POST["uid"];
	$query = "SELECT * FROM mill WHERE uniqueid='$uniqueid'";
	$result = mysqli_query($con,$query);
	$numrows = mysqli_num_rows($result);
	if($numrows!=0){
		$row = mysqli_fetch_assoc($result);
		$district = $row['district'];
		$name = $row['name'];
		$mill_id = $row['mill_id'];
		$milling_center = $row['milling_center'];
		$milling_process = $row['milling_process'];
		$latitude = $row['latitude'];
		$longitude = $row['longitude'];
		$active = $row['active'];
	}
	else{
		header("Location:Mill.php");
	}
}
else{
	header("Location:Mill.php");
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

<script>
	function setSelectedValue(obj_value,valueToSet) {
		var obj = document.getElementById(obj_value);
		for (var i = 0; i < obj.options.length; i++) {
			if (obj.options[i].value== valueToSet) {
				obj.options[i].selected = true;
				return;
			}
		}
	}
</script>
                <!-- START BREADCRUMB -->
                <ul class="breadcrumb">
                    <li><a href="Mill.php">Home</a></li>
                    <li class="active">Mill Edit</li>
                </ul>
                <!-- END BREADCRUMB -->


				<!-- PAGE CONTENT WRAPPER -->
                 <div class="page-content-wrap">

                    <div class="row">
                        <div class="col-md-12">

                            <form action="api/MillEdit.php" method="POST" class="form-horizontal" enctype = "multipart/form-data">
                            <div class="panel panel-default">
                               <div class="panel-body">
                                    <p>Fill this form to edit Mill.</p>
                                </div>

                             <div class="panel-body">

                                    <div class="row">

                                        <div class="col-md-6">
										
										<input type="hidden" id="uniqueid" name="uniqueid" value="<?php  echo $_POST["uid"] ?>" />
										<input type="hidden" id="active" name="active" value="<?php  echo $active ?>" />

										<div class="form-group">
                                                <label class="col-md-3 control-label">Mill District*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
												   <span class="input-group-addon"><span class="fa fa-arrow-down"></span></span>
                                                    <select class="form-control" id="district" name="district">
                                                    </select>
												</div>
                                                    <span class="help-block">District</span>
                                                </div>
                                            </div>

										<div class="form-group">
                                                <label class="col-md-3 control-label">Mill Name*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo $name ?>" required />
                                                    </div>
                                                    <span class="help-block">Mill Name</span>
                                                </div>
                                            </div>

										<div class="form-group">
                                                <label class="col-md-3 control-label">Mill ID*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="mill_id" name="mill_id" value="<?php echo $mill_id ?>" style="color: black; font-weight: bold;" readonly required />
                                                    </div>
                                                    <span class="help-block">Mill ID (readonly)</span>
                                                </div>
                                            </div>

										<div class="form-group">
                                                <label class="col-md-3 control-label">Milling Centre*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
												   <span class="input-group-addon"><span class="fa fa-arrow-down"></span></span>
                                                    <select class="form-control" id="milling_center" name="milling_center">
                                                    </select>
													</div>
                                                    <span class="help-block">Milling Centre Name</span>
                                                </div>
                                            </div>

                                        </div>
                                        <div class="col-md-6">

										<div class="form-group">
                                                <label class="col-md-3 control-label">Milling Process*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="milling_process" name="milling_process" value="<?php echo $milling_process ?>" required />
                                                    </div>
                                                    <span class="help-block">Milling Process</span>
                                                </div>
                                            </div>

										<div class="form-group">
                                                <label class="col-md-3 control-label">Mill Latitude*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="latitude" name="latitude" value="<?php echo $latitude ?>" required />
                                                    </div>
                                                    <span class="help-block">Latitude</span>
                                                </div>
                                            </div>
										
										<div class="form-group">
                                                <label class="col-md-3 control-label">Mill Longitude*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="longitude" name="longitude" value="<?php echo $longitude ?>" required />
                                                    </div>
                                                    <span class="help-block">Longitude</span>
                                                </div>
                                            </div>
									   
                                        </div>

                                    </div>

                                </div>
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
		<?php
			require('DistrictAutocomplete.php');
			echo "<script>setSelectedValue('district','$district'); </script>";
			require('MillingCenterAutocomplete.php');
			echo "<script>setSelectedValue('milling_center','$milling_center'); </script>";
		?>
		
		<script>
		function showPopup() {
            
			var district = document.getElementById('district').value;
			var name = document.getElementById('name').value;
			var mill_id = document.getElementById('mill_id').value;
			var milling_center = document.getElementById('milling_center').value;
			var milling_process = document.getElementById('milling_process').value;
			var latitude = document.getElementById('latitude').value;
            var longitude = document.getElementById('longitude').value;

            if (district === '' || name === '' || mill_id === '' || milling_center === '' || milling_process === '' || latitude === '' || longitude === '') {
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
