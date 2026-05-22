<?php

class PC {
    public $district;
    public $name;
    public $PC_ID;
    public $latitude;
    public $longitude;
    public $Paddy_Procurement;
    public $milling_center;
    public $uniqueid;
    public $active;

    // Getter methods

    public function getDistrict() {
        return $this->district;
    }

    public function getName() {
        return $this->name;
    }

    public function getPCID() {
        return $this->PC_ID;
    }

    public function getLatitude() {
        return $this->latitude;
    }

    public function getLongitude() {
        return $this->longitude;
    }

    public function getPaddyProcurement() {
        return $this->Paddy_Procurement;
    }

    public function getMillingCenter() {
        return $this->milling_center;
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

    public function setPCID($PC_ID) {
        $this->PC_ID = $PC_ID;
    }

    public function setLatitude($latitude) {
        $this->latitude = $latitude;
    }

    public function setLongitude($longitude) {
        $this->longitude = $longitude;
    }

    public function setPaddyProcurement($Paddy_Procurement) {
        $this->Paddy_Procurement = $Paddy_Procurement;
    }

    public function setMillingCenter($milling_center) {
        $this->milling_center = $milling_center;
    }

    public function setUniqueid($uniqueid) {
        $this->uniqueid = $uniqueid;
    }

    public function setActive($active) {
        $this->active = $active;
    }

    function insert(PC $pc){
        return "INSERT INTO pc (district, name, PC_ID, latitude, longitude, Paddy_Procurement, milling_center, uniqueid, active) VALUES ('".$pc->getDistrict()."','".$pc->getName()."','".$pc->getPCID()."','".$pc->getLatitude()."','".$pc->getLongitude()."','".$pc->getPaddyProcurement()."','".$pc->getMillingCenter()."','".$pc->getUniqueid()."','".$pc->getActive()."')";
    }

    function delete(PC $pc){
        return "DELETE FROM pc WHERE uniqueid='".$pc->getUniqueid()."'";
    }

    function deleteall(PC $pc){
        return "DELETE FROM pc WHERE 1";
    }

    function logname(PC $pc){
        return "SELECT name FROM pc WHERE uniqueid='".$pc->getUniqueid()."'";
    }

    function check(PC $pc){
        return "SELECT * FROM pc WHERE uniqueid='".$pc->getUniqueid()."'";
    }

    function checkInsert(PC $pc){
        return "SELECT * FROM pc WHERE LOWER(PC_ID)=LOWER('".$pc->getPCID()."')";
    }

    function checkEdit(PC $pc){
        return "SELECT * FROM pc WHERE LOWER(PC_ID)=LOWER('".$pc->getPCID()."')";
    }

    function update(PC $pc){
        return "UPDATE pc SET district = '".$pc->getDistrict()."', name = '".$pc->getName()."', PC_ID = '".$pc->getPCID()."', latitude = '".$pc->getLatitude()."', longitude = '".$pc->getLongitude()."', Paddy_Procurement = '".$pc->getPaddyProcurement()."', milling_center = '".$pc->getMillingCenter()."', active = '".$pc->getActive()."' WHERE uniqueid = '".$pc->getUniqueid()."'";
    }

    function updateEdit(PC $pc){
        return "UPDATE pc SET district = '".$pc->getDistrict()."', name = '".$pc->getName()."', latitude = '".$pc->getLatitude()."', longitude = '".$pc->getLongitude()."', Paddy_Procurement = '".$pc->getPaddyProcurement()."', milling_center = '".$pc->getMillingCenter()."', active = '".$pc->getActive()."' WHERE PC_ID = '".$pc->getPCID()."'";
    }
}

?>
