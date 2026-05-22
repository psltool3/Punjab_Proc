<?php

class Weighbridge {
    public $District;
    public $Storage_Point;
    public $Name;
    public $ID;
    public $Latitude;
    public $Longitude;
    public $Capacity;
    public $uniqueid;
    public $active;

    // Getter methods

    public function getDistrict() {
        return $this->District;
    }

    public function getStoragePoint() {
        return $this->Storage_Point;
    }

    public function getName() {
        return $this->Name;
    }

    public function getId() {
        return $this->ID;
    }

    public function getLatitude() {
        return $this->Latitude;
    }

    public function getLongitude() {
        return $this->Longitude;
    }

    public function getCapacity() {
        return $this->Capacity;
    }

    public function getUniqueid() {
        return $this->uniqueid;
    }

    public function getActive() {
        return $this->active;
    }

    // Setter methods

    public function setDistrict($District) {
        $this->District = $District;
    }

    public function setStoragePoint($Storage_Point) {
        $this->Storage_Point = $Storage_Point;
    }

    public function setName($Name) {
        $this->Name = $Name;
    }

    public function setId($ID) {
        $this->ID = $ID;
    }

    public function setLatitude($Latitude) {
        $this->Latitude = $Latitude;
    }

    public function setLongitude($Longitude) {
        $this->Longitude = $Longitude;
    }

    public function setCapacity($Capacity) {
        $this->Capacity = $Capacity;
    }

    public function setUniqueid($uniqueid) {
        $this->uniqueid = $uniqueid;
    }

    public function setActive($active) {
        $this->active = $active;
    }

    function insert(Weighbridge $weighbridge){
        return "INSERT INTO weighbridge (District, Storage_Point, Name, ID, Latitude, Longitude, Capacity, uniqueid, active) VALUES ('".$weighbridge->getDistrict()."','".$weighbridge->getStoragePoint()."','".$weighbridge->getName()."','".$weighbridge->getId()."','".$weighbridge->getLatitude()."','".$weighbridge->getLongitude()."','".$weighbridge->getCapacity()."','".$weighbridge->getUniqueid()."','".$weighbridge->getActive()."')";
    }

    function delete(Weighbridge $weighbridge){
        return "DELETE FROM weighbridge WHERE uniqueid='".$weighbridge->getUniqueid()."'";
    }

    function deleteall(Weighbridge $weighbridge){
        return "DELETE FROM weighbridge WHERE 1";
    }

    function logname(Weighbridge $weighbridge){
        return "SELECT Name FROM weighbridge WHERE uniqueid='".$weighbridge->getUniqueid()."'";
    }

    function check(Weighbridge $weighbridge){
        return "SELECT * FROM weighbridge WHERE uniqueid='".$weighbridge->getUniqueid()."'";
    }

    function checkInsert(Weighbridge $weighbridge){
        return "SELECT * FROM weighbridge WHERE LOWER(ID)=LOWER('".$weighbridge->getId()."')";
    }

    function checkEdit(Weighbridge $weighbridge){
        return "SELECT * FROM weighbridge WHERE LOWER(ID)=LOWER('".$weighbridge->getId()."')";
    }

    function update(Weighbridge $weighbridge){
        return "UPDATE weighbridge SET District = '".$weighbridge->getDistrict()."',Storage_Point = '".$weighbridge->getStoragePoint()."',Name = '".$weighbridge->getName()."',ID = '".$weighbridge->getId()."',Latitude = '".$weighbridge->getLatitude()."',Longitude = '".$weighbridge->getLongitude()."',Capacity = '".$weighbridge->getCapacity()."',active = '".$weighbridge->getActive()."' WHERE uniqueid = '".$weighbridge->getUniqueid()."'";
    }

    function updateEdit(Weighbridge $weighbridge){
        return "UPDATE weighbridge SET District = '".$weighbridge->getDistrict()."',Storage_Point = '".$weighbridge->getStoragePoint()."',Name = '".$weighbridge->getName()."',Latitude = '".$weighbridge->getLatitude()."',Longitude = '".$weighbridge->getLongitude()."',Capacity = '".$weighbridge->getCapacity()."',active = '".$weighbridge->getActive()."' WHERE ID = '".$weighbridge->getId()."'";
    }
}

?>
