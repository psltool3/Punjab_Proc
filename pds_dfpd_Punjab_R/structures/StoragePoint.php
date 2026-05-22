<?php

class StoragePoint {
    public $district;
    public $name;
    public $id;
    public $warehousetype;
    public $type;
    public $latitude;
    public $longitude;
    public $capacity;
    public $uniqueid;
    public $active;

    // Getter methods

    public function getDistrict() {
        return $this->district;
    }

    public function getName() {
        return $this->name;
    }

    public function getId() {
        return $this->id;
    }

    public function getWarehousetype() {
        return $this->warehousetype;
    }

    public function getType() {
        return $this->type;
    }

    public function getLatitude() {
        return $this->latitude;
    }

    public function getLongitude() {
        return $this->longitude;
    }

    public function getCapacity() {
        return $this->capacity;
    }
	
	public function getUniqueid() {
        return $this->uniqueid;
    }
	
	public function getActive() {
        return $this->active;
    }


    // Setter methods

    public function setDistrict($district) {
        $this->district = $district;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function setWarehousetype($warehousetype) {
        $this->warehousetype = $warehousetype;
    }

    public function setType($type) {
        $this->type = $type;
    }

    public function setLatitude($latitude) {
        $this->latitude = $latitude;
    }

    public function setLongitude($longitude) {
        $this->longitude = $longitude;
    }

    public function setCapacity($capacity) {
        $this->capacity = $capacity;
    }
	
	public function setUniqueid($uniqueid) {
        $this->uniqueid = $uniqueid;
    }
	
	public function setActive($active) {
        $this->active = $active;
    }
	
	function insert(StoragePoint $storagePoint){
        return "INSERT INTO storage_point (district, name, id, warehousetype, type, latitude, longitude, capacity, uniqueid, active) VALUES ('".$storagePoint->getDistrict()."','".$storagePoint->getName()."','".$storagePoint->getId()."','".$storagePoint->getWarehousetype()."','".$storagePoint->getType()."','".$storagePoint->getLatitude()."','".$storagePoint->getLongitude()."','".$storagePoint->getCapacity()."','".$storagePoint->getUniqueid()."','".$storagePoint->getActive()."')";
    }

    function delete(StoragePoint $storagePoint){
        return "DELETE FROM storage_point WHERE uniqueid='".$storagePoint->getUniqueid()."'";
    }
	
	function deleteall(StoragePoint $storagePoint){
        return "DELETE FROM storage_point WHERE 1";
    }
	
	function logname(StoragePoint $storagePoint){

        return "SELECT name FROM storage_point WHERE uniqueid='".$storagePoint->getUniqueid()."'";

    }
	
	function check(StoragePoint $storagePoint){
        return "SELECT * FROM storage_point WHERE uniqueid='".$storagePoint->getUniqueid()."'";
    }
	
	function checkInsert(StoragePoint $storagePoint){
        return "SELECT * FROM storage_point WHERE LOWER(id)=LOWER('".$storagePoint->getId()."')";
    }
	
	function checkEdit(StoragePoint $storagePoint){
        return "SELECT * FROM storage_point WHERE LOWER(id)=LOWER('".$storagePoint->getId()."')";
    }

    function update(StoragePoint $storagePoint){
      return  "UPDATE storage_point SET district = '".$storagePoint->getDistrict()."',name = '".$storagePoint->getName()."',id = '".$storagePoint->getId()."',warehousetype = '".$storagePoint->getWarehousetype()."',type = '".$storagePoint->getType()."',latitude = '".$storagePoint->getLatitude()."',longitude = '".$storagePoint->getLongitude()."',capacity = '".$storagePoint->getCapacity()."',active = '".$storagePoint->getActive()."' WHERE uniqueid = '".$storagePoint->getUniqueid()."'";
    }
	
	function updateEdit(StoragePoint $storagePoint){
      return  "UPDATE storage_point SET district = '".$storagePoint->getDistrict()."',name = '".$storagePoint->getName()."',warehousetype = '".$storagePoint->getWarehousetype()."',type = '".$storagePoint->getType()."',latitude = '".$storagePoint->getLatitude()."',longitude = '".$storagePoint->getLongitude()."',capacity = '".$storagePoint->getCapacity()."',active = '".$storagePoint->getActive()."' WHERE id = '".$storagePoint->getId()."'";
    }
}

?>
