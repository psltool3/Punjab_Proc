<?php

class Mill {
    public $district;
    public $name;
    public $mill_id;
    public $latitude;
    public $longitude;
    public $milling_center;
    public $milling_process;
    public $uniqueid;
    public $active;

    // Getter methods

    public function getDistrict() {
        return $this->district;
    }

    public function getName() {
        return $this->name;
    }

    public function getMillId() {
        return $this->mill_id;
    }

    public function getLatitude() {
        return $this->latitude;
    }

    public function getLongitude() {
        return $this->longitude;
    }

    public function getMillingCenter() {
        return $this->milling_center;
    }

    public function getMillingProcess() {
        return $this->milling_process;
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

    public function setMillId($mill_id) {
        $this->mill_id = $mill_id;
    }

    public function setLatitude($latitude) {
        $this->latitude = $latitude;
    }

    public function setLongitude($longitude) {
        $this->longitude = $longitude;
    }

    public function setMillingCenter($milling_center) {
        $this->milling_center = $milling_center;
    }

    public function setMillingProcess($milling_process) {
        $this->milling_process = $milling_process;
    }

    public function setUniqueid($uniqueid) {
        $this->uniqueid = $uniqueid;
    }

    public function setActive($active) {
        $this->active = $active;
    }

    function insert(Mill $mill){
        return "INSERT INTO mill (district, name, mill_id, latitude, longitude, milling_center, milling_process, uniqueid, active) VALUES ('".$mill->getDistrict()."','".$mill->getName()."','".$mill->getMillId()."','".$mill->getLatitude()."','".$mill->getLongitude()."','".$mill->getMillingCenter()."','".$mill->getMillingProcess()."','".$mill->getUniqueid()."','".$mill->getActive()."')";
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
        return "SELECT * FROM mill WHERE LOWER(mill_id)=LOWER('".$mill->getMillId()."')";
    }

    function checkEdit(Mill $mill){
        return "SELECT * FROM mill WHERE LOWER(mill_id)=LOWER('".$mill->getMillId()."')";
    }

    function update(Mill $mill){
        return "UPDATE mill SET district = '".$mill->getDistrict()."', name = '".$mill->getName()."', mill_id = '".$mill->getMillId()."', latitude = '".$mill->getLatitude()."', longitude = '".$mill->getLongitude()."', milling_center = '".$mill->getMillingCenter()."', milling_process = '".$mill->getMillingProcess()."', active = '".$mill->getActive()."' WHERE uniqueid = '".$mill->getUniqueid()."'";
    }

    function updateEdit(Mill $mill){
        return "UPDATE mill SET district = '".$mill->getDistrict()."', name = '".$mill->getName()."', latitude = '".$mill->getLatitude()."', longitude = '".$mill->getLongitude()."', milling_center = '".$mill->getMillingCenter()."', milling_process = '".$mill->getMillingProcess()."', active = '".$mill->getActive()."' WHERE mill_id = '".$mill->getMillId()."'";
    }
}

?>
