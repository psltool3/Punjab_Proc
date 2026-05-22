<?php

class PC {
    public $district;
    public $Block;
    public $name;
    public $PPC_Code;
    public $latitude;
    public $longitude;
    public $Quantity;
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

    public function getPPCCode() {
        return $this->PPC_Code;
    }

    public function getLatitude() {
        return $this->latitude;
    }

    public function getLongitude() {
        return $this->longitude;
    }

    public function getQuantity() {
        return $this->Quantity;
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

    public function setPPCCode($PPC_Code) {
        $this->PPC_Code = $PPC_Code;
    }

    public function setLatitude($latitude) {
        $this->latitude = $latitude;
    }

    public function setLongitude($longitude) {
        $this->longitude = $longitude;
    }

    public function setQuantity($Quantity) {
        $this->Quantity = $Quantity;
    }

    public function setUniqueid($uniqueid) {
        $this->uniqueid = $uniqueid;
    }

    public function setActive($active) {
        $this->active = $active;
    }

    function insert(PC $pc){
        return "INSERT INTO pc (district, Block, name, PPC_Code, latitude, longitude, Quantity, uniqueid, active) VALUES ('".$pc->getDistrict()."','".$pc->getBlock()."','".$pc->getName()."','".$pc->getPPCCode()."','".$pc->getLatitude()."','".$pc->getLongitude()."','".$pc->getQuantity()."','".$pc->getUniqueid()."','".$pc->getActive()."')";
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
        return "SELECT * FROM pc WHERE LOWER(PPC_Code)=LOWER('".$pc->getPPCCode()."')";
    }

    function checkEdit(PC $pc){
        return "SELECT * FROM pc WHERE LOWER(PPC_Code)=LOWER('".$pc->getPPCCode()."')";
    }

    function update(PC $pc){
        return "UPDATE pc SET district = '".$pc->getDistrict()."', Block = '".$pc->getBlock()."', name = '".$pc->getName()."', PPC_Code = '".$pc->getPPCCode()."', latitude = '".$pc->getLatitude()."', longitude = '".$pc->getLongitude()."', Quantity = '".$pc->getQuantity()."', active = '".$pc->getActive()."' WHERE uniqueid = '".$pc->getUniqueid()."'";
    }

    function updateEdit(PC $pc){
        return "UPDATE pc SET district = '".$pc->getDistrict()."', Block = '".$pc->getBlock()."', name = '".$pc->getName()."', latitude = '".$pc->getLatitude()."', longitude = '".$pc->getLongitude()."', Quantity = '".$pc->getQuantity()."', active = '".$pc->getActive()."' WHERE PPC_Code = '".$pc->getPPCCode()."'";
    }
}

?>
