<?php

class Warehouse {
    public $district;
    public $name;
    public $id;
    public $warehousetype;
    public $latitude;
    public $longitude;
    public $Storage_Point;
    public $Capacity_Available;
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

    public function getLatitude() {
        return $this->latitude;
    }

    public function getLongitude() {
        return $this->longitude;
    }

    public function getStoragePoint() {
        return $this->Storage_Point;
    }

    public function getCapacityAvailable() {
        return $this->Capacity_Available;
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

    public function setLatitude($latitude) {
        $this->latitude = $latitude;
    }

    public function setLongitude($longitude) {
        $this->longitude = $longitude;
    }

    public function setStoragePoint($Storage_Point) {
        $this->Storage_Point = $Storage_Point;
    }

    public function setCapacityAvailable($Capacity_Available) {
        $this->Capacity_Available = $Capacity_Available;
    }

    public function setUniqueid($uniqueid) {
        $this->uniqueid = $uniqueid;
    }

    public function setActive($active) {
        $this->active = $active;
    }

    function insert(Warehouse $warehouse){
        return "INSERT INTO warehouse (district, name, id, warehousetype, latitude, longitude, Storage_Point, Capacity_Available, uniqueid, active) VALUES ('".$warehouse->getDistrict()."','".$warehouse->getName()."','".$warehouse->getId()."','".$warehouse->getWarehousetype()."','".$warehouse->getLatitude()."','".$warehouse->getLongitude()."','".$warehouse->getStoragePoint()."','".$warehouse->getCapacityAvailable()."','".$warehouse->getUniqueid()."','".$warehouse->getActive()."')";
    }

    function delete(Warehouse $warehouse){
        return "DELETE FROM warehouse WHERE uniqueid='".$warehouse->getUniqueid()."'";
    }

    function deleteall(Warehouse $warehouse){
        return "DELETE FROM warehouse WHERE 1";
    }

    function deletealldistrict(Warehouse $warehouse, $district){
        return "DELETE FROM warehouse WHERE district='$district'";
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
        return "UPDATE warehouse SET district = '".$warehouse->getDistrict()."',name = '".$warehouse->getName()."',id = '".$warehouse->getId()."',warehousetype = '".$warehouse->getWarehousetype()."',latitude = '".$warehouse->getLatitude()."',longitude = '".$warehouse->getLongitude()."',Storage_Point = '".$warehouse->getStoragePoint()."',Capacity_Available = '".$warehouse->getCapacityAvailable()."',active = '".$warehouse->getActive()."' WHERE uniqueid = '".$warehouse->getUniqueid()."'";
    }

    function updateEdit(Warehouse $warehouse){
        return "UPDATE warehouse SET district = '".$warehouse->getDistrict()."',name = '".$warehouse->getName()."',warehousetype = '".$warehouse->getWarehousetype()."',latitude = '".$warehouse->getLatitude()."',longitude = '".$warehouse->getLongitude()."',Storage_Point = '".$warehouse->getStoragePoint()."',Capacity_Available = '".$warehouse->getCapacityAvailable()."',active = '".$warehouse->getActive()."' WHERE id = '".$warehouse->getId()."'";
    }
}

?>