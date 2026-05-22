<?php

class Warehouse {
    public $district;
    public $name;
    public $id;
    public $warehousetype;
    public $type;
    public $latitude;
    public $longitude;
    public $normal_rice;
    public $state_frk_rice;
    public $central_frk_rice;
    public $storage_rice;
    public $storage_state_frk_rice;
    public $storage_central_frk_rice;
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

    public function getNormalRice() {
        return $this->normal_rice;
    }

    public function getStateFrkRice() {
        return $this->state_frk_rice;
    }

    public function getCentralFrkRice() {
        return $this->central_frk_rice;
    }

    public function getStorageRice() {
        return $this->storage_rice;
    }

    public function getStorageStateFrkRice() {
        return $this->storage_state_frk_rice;
    }

    public function getStorageCentralFrkRice() {
        return $this->storage_central_frk_rice;
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

    public function setNormalRice($normal_rice) {
        $this->normal_rice = $normal_rice;
    }

    public function setStateFrkRice($state_frk_rice) {
        $this->state_frk_rice = $state_frk_rice;
    }

    public function setCentralFrkRice($central_frk_rice) {
        $this->central_frk_rice = $central_frk_rice;
    }

    public function setStorageRice($storage_rice) {
        $this->storage_rice = $storage_rice;
    }

    public function setStorageStateFrkRice($storage_state_frk_rice) {
        $this->storage_state_frk_rice = $storage_state_frk_rice;
    }

    public function setStorageCentralFrkRice($storage_central_frk_rice) {
        $this->storage_central_frk_rice = $storage_central_frk_rice;
    }
	
	public function setUniqueid($uniqueid) {
        $this->uniqueid = $uniqueid;
    }
	
	public function setActive($active) {
        $this->active = $active;
    }
	
	function insert(Warehouse $warehouse){
        return "INSERT INTO warehouse (district, name, id, warehousetype, type, latitude, longitude, normal_rice, state_frk_rice, central_frk_rice, storage_rice, storage_state_frk_rice, storage_central_frk_rice, uniqueid, active) VALUES ('".$warehouse->getDistrict()."','".$warehouse->getName()."','".$warehouse->getId()."','".$warehouse->getWarehousetype()."','".$warehouse->getType()."','".$warehouse->getLatitude()."','".$warehouse->getLongitude()."','".$warehouse->getNormalRice()."','".$warehouse->getStateFrkRice()."','".$warehouse->getCentralFrkRice()."','".$warehouse->getStorageRice()."','".$warehouse->getStorageStateFrkRice()."','".$warehouse->getStorageCentralFrkRice()."','".$warehouse->getUniqueid()."','".$warehouse->getActive()."')";
    }

    function delete(Warehouse $warehouse){
        return "DELETE FROM warehouse WHERE uniqueid='".$warehouse->getUniqueid()."'";
    }
	
	function deleteall(Warehouse $warehouse){
        return "DELETE FROM warehouse WHERE 1";
    }
	
	function logname(Warehouse $warehouse){
        return "SELECT name FROM warehouse WHERE uniqueid='".$warehouse->getUniqueid()."'";
    }
	
	function check(Warehouse $warehouse){
        return "SELECT * FROM warehouse WHERE uniqueid='".$warehouse->getUniqueid()."'";
    }
	
	function checkInsert(Warehouse $warehouse){
        return "SELECT * FROM warehouse WHERE LOWER(id)=LOWER('".$warehouse->getId()."')";
    }
	
	function checkEdit(Warehouse $warehouse){
        return "SELECT * FROM warehouse WHERE LOWER(id)=LOWER('".$warehouse->getId()."')";
    }

    function update(Warehouse $warehouse){
      return  "UPDATE warehouse SET district = '".$warehouse->getDistrict()."',name = '".$warehouse->getName()."',id = '".$warehouse->getId()."',warehousetype = '".$warehouse->getWarehousetype()."',type = '".$warehouse->getType()."',latitude = '".$warehouse->getLatitude()."',longitude = '".$warehouse->getLongitude()."',normal_rice = '".$warehouse->getNormalRice()."',state_frk_rice = '".$warehouse->getStateFrkRice()."',central_frk_rice = '".$warehouse->getCentralFrkRice()."',storage_rice = '".$warehouse->getStorageRice()."',storage_state_frk_rice = '".$warehouse->getStorageStateFrkRice()."',storage_central_frk_rice = '".$warehouse->getStorageCentralFrkRice()."',active = '".$warehouse->getActive()."' WHERE uniqueid = '".$warehouse->getUniqueid()."'";
    }
	
	function updateEdit(Warehouse $warehouse){
      return  "UPDATE warehouse SET district = '".$warehouse->getDistrict()."',name = '".$warehouse->getName()."',warehousetype = '".$warehouse->getWarehousetype()."',type = '".$warehouse->getType()."',latitude = '".$warehouse->getLatitude()."',longitude = '".$warehouse->getLongitude()."',normal_rice = '".$warehouse->getNormalRice()."',state_frk_rice = '".$warehouse->getStateFrkRice()."',central_frk_rice = '".$warehouse->getCentralFrkRice()."',storage_rice = '".$warehouse->getStorageRice()."',storage_state_frk_rice = '".$warehouse->getStorageStateFrkRice()."',storage_central_frk_rice = '".$warehouse->getStorageCentralFrkRice()."',active = '".$warehouse->getActive()."' WHERE id = '".$warehouse->getId()."'";
    }
}

?>