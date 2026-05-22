<?php
require('util/Connection.php');
require('util/SessionCheck.php');
require('Header.php');

$district = ucfirst($_SESSION["district_district"]);

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
                    <li><a href="Mill.php">Home</a></li>
                    <li class="active">Mill Add</li>
                </ul>
                <!-- END BREADCRUMB -->


				<!-- PAGE CONTENT WRAPPER -->
                 <div class="page-content-wrap">

                    <div class="row">
                        <div class="col-md-12">

                            <form action="api/MillAdd.php" method="POST" class="form-horizontal" enctype = "multipart/form-data">
                            <div class="panel panel-default">
                               <div class="panel-body">
                                    <p>Fill this form to add new mill.</p>
                                </div>

                             <div class="panel-body">

                                    <div class="row">

                                        <div class="col-md-6">
										
											<div class="form-group">
                                                <label class="col-md-3 control-label">Name of Mill*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="name" name="name" required />
                                                    </div>
                                                    <span class="help-block">Mill Name</span>
                                                </div>
                                            </div>
											
											<div class="form-group">
                                                <label class="col-md-3 control-label">Mill Type</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
												   <span class="input-group-addon"><span class="fa fa-arrow-down"></span></span>
                                                    <select class="form-control" id="type" name="type">
													<option value="RAW">RAW</option>
													<option value="PARA">PARA</option>
													
                                                    </select>
													</div>
                                                    <span class="help-block">Mill Type</span>
                                                </div>
                                            </div>
											
											<div class="form-group">
                                                <label class="col-md-3 control-label">Latitude*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="latitude" name="latitude" required />
                                                    </div>
                                                    <span class="help-block">Latitude</span>
                                                </div>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label class="col-md-3 control-label">Longitude*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="longitude" name="longitude" required />
                                                    </div>
                                                    <span class="help-block">Longitude</span>
                                                </div>
                                            </div>
											
											
                                        </div>
                                        <div class="col-md-6">
										
											<div class="form-group">
                                                <label class="col-md-3 control-label">District*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="district" name="district" value="<?php echo $district ?>" readonly />
                                                    </div>
                                                    <span class="help-block">District</span>
                                                </div>
                                            </div>
										
											<div class="form-group">
                                                <label class="col-md-3 control-label">Mill Id*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="id" name="id" required />
                                                    </div>
                                                    <span class="help-block">Mill ID</span>
                                                </div>
                                            </div>
											
                                            <div class="form-group">
                                                <label class="col-md-3 control-label">Milling Capacity*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="milling_capacity" name="milling_capacity" required />
                                                    </div>
                                                    <span class="help-block">Milling Capacity</span>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="col-md-3 control-label">Performance Factor*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="performance_factor" name="performance_factor" required />
                                                    </div>
                                                    <span class="help-block">Performance Factor</span>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="col-md-3 control-label">Inventory Raw Rice*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="Inventory_Raw_Rice" name="Inventory_Raw_Rice" required />
                                                    </div>
                                                    <span class="help-block">Inventory Raw Rice</span>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="col-md-3 control-label">Inventory Para Rice*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="Inventory_Para_Rice" name="Inventory_Para_Rice" required />
                                                    </div>
                                                    <span class="help-block">Inventory Para Rice</span>
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
		
		<script>
		function showPopup() {
            
			var name = document.getElementById('name').value;
            var type = document.getElementById('type').value;
			var latitude = document.getElementById('latitude').value;
            var longitude = document.getElementById('longitude').value;
			var id = document.getElementById('id').value;
            var district = document.getElementById('district').value;
            var milling_capacity = document.getElementById('milling_capacity').value;
            var Inventory_Raw_Rice = document.getElementById('Inventory_Raw_Rice').value;
            var Inventory_Para_Rice = document.getElementById('Inventory_Para_Rice').value;
            var performance_factor = document.getElementById('performance_factor').value;

            if (name === '' || type === '' || latitude === '' || longitude === '' || id === '' || district === '' || milling_capacity === '' || Inventory_Raw_Rice === '' || Inventory_Para_Rice === '' || performance_factor === '') {
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
