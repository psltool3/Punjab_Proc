<?php

class Mill {
    public $district;
    public $name;
    public $id;
    public $type;
    public $latitude;
    public $longitude;
    public $milling_capacity;
    public $inventory_raw_rice;
    public $inventory_para_rice;
    public $performance_factor;
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

    public function getType() {
        return $this->type;
    }

    public function getLatitude() {
        return $this->latitude;
    }

    public function getLongitude() {
        return $this->longitude;
    }

    public function getMillingCapacity() {
        return $this->milling_capacity;
    }

    public function getInventoryRawRice() {
        return $this->inventory_raw_rice;
    }

    public function getInventoryParaRice() {
        return $this->inventory_para_rice;
    }

    public function getPerformanceFactor() {
        return $this->performance_factor;
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

    public function setType($type) {
        $this->type = $type;
    }

    public function setLatitude($latitude) {
        $this->latitude = $latitude;
    }

    public function setLongitude($longitude) {
        $this->longitude = $longitude;
    }

    public function setMillingCapacity($milling_capacity) {
        $this->milling_capacity = $milling_capacity;
    }

    public function setInventoryRawRice($inventory_raw_rice) {
        $this->inventory_raw_rice = $inventory_raw_rice;
    }

    public function setInventoryParaRice($inventory_para_rice) {
        $this->inventory_para_rice = $inventory_para_rice;
    }

    public function setPerformanceFactor($performance_factor) {
        $this->performance_factor = $performance_factor;
    }

    public function setUniqueid($uniqueid) {
        $this->uniqueid = $uniqueid;
    }

    public function setActive($active) {
        $this->active = $active;
    }

    function insert(Mill $mill){
        return "INSERT INTO mill (district, name, id, type, latitude, longitude, milling_capacity, Inventory_Raw_Rice, Inventory_Para_Rice, performance_factor, uniqueid, active) VALUES ('".$mill->getDistrict()."','".$mill->getName()."','".$mill->getId()."','".$mill->getType()."','".$mill->getLatitude()."','".$mill->getLongitude()."','".$mill->getMillingCapacity()."','".$mill->getInventoryRawRice()."','".$mill->getInventoryParaRice()."','".$mill->getPerformanceFactor()."','".$mill->getUniqueid()."','".$mill->getActive()."')";
    }

    function delete(Mill $mill){
        return "DELETE FROM mill WHERE uniqueid='".$mill->getUniqueid()."'";
    }

    function deleteall(Mill $mill){
        return "DELETE FROM mill WHERE 1";
    }

    function logname(Mill $mill){
        return "SELECT name FROM mill WHERE uniqueid='".$mill->getUniqueid()."'";
    }

    function check(Mill $mill){
        return "SELECT * FROM mill WHERE uniqueid='".$mill->getUniqueid()."'";
    }

    function checkInsert(Mill $mill){
        return "SELECT * FROM mill WHERE LOWER(id)=LOWER('".$mill->getId()."')";
    }

    function checkEdit(Mill $mill){
        return "SELECT * FROM mill WHERE LOWER(id)=LOWER('".$mill->getId()."')";
    }

    function update(Mill $mill){
        return "UPDATE mill SET district = '".$mill->getDistrict()."',name = '".$mill->getName()."',id = '".$mill->getId()."',type = '".$mill->getType()."',latitude = '".$mill->getLatitude()."',longitude = '".$mill->getLongitude()."',milling_capacity = '".$mill->getMillingCapacity()."',Inventory_Raw_Rice = '".$mill->getInventoryRawRice()."',Inventory_Para_Rice = '".$mill->getInventoryParaRice()."',performance_factor = '".$mill->getPerformanceFactor()."',active = '".$mill->getActive()."' WHERE uniqueid = '".$mill->getUniqueid()."'";
    }

    function updateEdit(Mill $mill){
        return "UPDATE mill SET district = '".$mill->getDistrict()."',name = '".$mill->getName()."',type = '".$mill->getType()."',latitude = '".$mill->getLatitude()."',longitude = '".$mill->getLongitude()."',milling_capacity = '".$mill->getMillingCapacity()."',Inventory_Raw_Rice = '".$mill->getInventoryRawRice()."',Inventory_Para_Rice = '".$mill->getInventoryParaRice()."',performance_factor = '".$mill->getPerformanceFactor()."',active = '".$mill->getActive()."' WHERE id = '".$mill->getId()."'";
    }
}

?>
