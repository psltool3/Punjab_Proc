<?php

class Mill {
    public $district;
    public $Block;
    public $name;
    public $mill_code;
    public $type;
    public $Storage_Capacity;
    public $milling_capacity;
    public $BG_Amount;
    public $SD_Norms;
    public $latitude;
    public $longitude;
    public $days;
    public $uniqueid;
    public $active;

    // Getter methods

    public function getDistrict() {
        return $this->district;
    }

    public function getBlock() {
        return $this->Block;
    }

    public function getName() {
        return $this->name;
    }

    public function getMillCode() {
        return $this->mill_code;
    }

    public function getType() {
        return $this->type;
    }

    public function getStorageCapacity() {
        return $this->Storage_Capacity;
    }

    public function getMillingCapacity() {
        return $this->milling_capacity;
    }

    public function getBGAmount() {
        return $this->BG_Amount;
    }

    public function getSDNorms() {
        return $this->SD_Norms;
    }

    public function getLatitude() {
        return $this->latitude;
    }

    public function getLongitude() {
        return $this->longitude;
    }

    public function getDays() {
        return $this->days;
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

    public function setBlock($Block) {
        $this->Block = $Block;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function setMillCode($mill_code) {
        $this->mill_code = $mill_code;
    }

    public function setType($type) {
        $this->type = $type;
    }

    public function setStorageCapacity($Storage_Capacity) {
        $this->Storage_Capacity = $Storage_Capacity;
    }

    public function setMillingCapacity($milling_capacity) {
        $this->milling_capacity = $milling_capacity;
    }

    public function setBGAmount($BG_Amount) {
        $this->BG_Amount = $BG_Amount;
    }

    public function setSDNorms($SD_Norms) {
        $this->SD_Norms = $SD_Norms;
    }

    public function setLatitude($latitude) {
        $this->latitude = $latitude;
    }

    public function setLongitude($longitude) {
        $this->longitude = $longitude;
    }

    public function setDays($days) {
        $this->days = $days;
    }

    public function setUniqueid($uniqueid) {
        $this->uniqueid = $uniqueid;
    }

    public function setActive($active) {
        $this->active = $active;
    }

    function insert(Mill $mill){
        return "INSERT INTO mill (district, Block, name, mill_code, type, Storage_Capacity, milling_capacity, BG_Amount, SD_Norms, latitude, longitude, days, uniqueid, active) VALUES ('".$mill->getDistrict()."','".$mill->getBlock()."','".$mill->getName()."','".$mill->getMillCode()."','".$mill->getType()."','".$mill->getStorageCapacity()."','".$mill->getMillingCapacity()."','".$mill->getBGAmount()."','".$mill->getSDNorms()."','".$mill->getLatitude()."','".$mill->getLongitude()."','".$mill->getDays()."','".$mill->getUniqueid()."','".$mill->getActive()."')";
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
        return "SELECT * FROM mill WHERE LOWER(mill_code)=LOWER('".$mill->getMillCode()."')";
    }

    function checkEdit(Mill $mill){
        return "SELECT * FROM mill WHERE LOWER(mill_code)=LOWER('".$mill->getMillCode()."')";
    }

    function update(Mill $mill){
        return "UPDATE mill SET district = '".$mill->getDistrict()."', Block = '".$mill->getBlock()."', name = '".$mill->getName()."', mill_code = '".$mill->getMillCode()."', type = '".$mill->getType()."', Storage_Capacity = '".$mill->getStorageCapacity()."', milling_capacity = '".$mill->getMillingCapacity()."', BG_Amount = '".$mill->getBGAmount()."', SD_Norms = '".$mill->getSDNorms()."', latitude = '".$mill->getLatitude()."', longitude = '".$mill->getLongitude()."', days = '".$mill->getDays()."', active = '".$mill->getActive()."' WHERE uniqueid = '".$mill->getUniqueid()."'";
    }

    function updateEdit(Mill $mill){
        return "UPDATE mill SET district = '".$mill->getDistrict()."', Block = '".$mill->getBlock()."', name = '".$mill->getName()."', type = '".$mill->getType()."', Storage_Capacity = '".$mill->getStorageCapacity()."', milling_capacity = '".$mill->getMillingCapacity()."', BG_Amount = '".$mill->getBGAmount()."', SD_Norms = '".$mill->getSDNorms()."', latitude = '".$mill->getLatitude()."', longitude = '".$mill->getLongitude()."', days = '".$mill->getDays()."', active = '".$mill->getActive()."' WHERE mill_code = '".$mill->getMillCode()."'";
    }
}

?>
