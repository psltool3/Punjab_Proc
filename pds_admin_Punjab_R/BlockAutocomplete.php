<?php

require('util/Connection.php');

 ?>

<script>

var y = document.getElementById("Block");

<?php
$query = "SELECT * FROM block ORDER BY name";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);

while($row = mysqli_fetch_assoc($result)){
	echo 'var option = document.createElement("option");';
	echo 'option.text = "'.$row['name'].'";';
	echo 'option.value = "'.$row['name'].'";';
	echo 'y.add(option);';
}

?>
</script>
