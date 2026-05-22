<?php

class PC {
    public $district;
    public $name;
    public $id;
    public $pctype;
    public $type;
    public $latitude;
    public $longitude;
    public $Paddy_Procurement;
    public $Storage_Point;
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

    public function getPctype() {
        return $this->pctype;
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

    public function getPaddyProcurement() {
        return $this->Paddy_Procurement;
    }

    public function getStoragePoint() {
        return $this->Storage_Point;
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

    public function setPctype($pctype) {
        $this->pctype = $pctype;
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

    public function setPaddyProcurement($Paddy_Procurement) {
        $this->Paddy_Procurement = $Paddy_Procurement;
    }

    public function setStoragePoint($Storage_Point) {
        $this->Storage_Point = $Storage_Point;
    }
	
	public function setUniqueid($uniqueid) {
        $this->uniqueid = $uniqueid;
    }
	
	public function setActive($active) {
        $this->active = $active;
    }
	
	function insert(PC $pc){
        return "INSERT INTO pc2 (district, name, id, latitude, longitude, Paddy_Procurement, Storage_Point, uniqueid, active) VALUES ('".$pc->getDistrict()."','".$pc->getName()."','".$pc->getId()."','".$pc->getLatitude()."','".$pc->getLongitude()."','".$pc->getPaddyProcurement()."','".$pc->getStoragePoint()."','".$pc->getUniqueid()."','".$pc->getActive()."')";
    }

    function delete(PC $pc){
        return "DELETE FROM pc2 WHERE uniqueid='".$pc->getUniqueid()."'";
    }
	
	function deleteall(PC $pc){
        return "DELETE FROM pc2 WHERE 1";
    }
	
	function logname(PC $pc){

        return "SELECT name FROM pc2 WHERE uniqueid='".$pc->getUniqueid()."'";

    }
	
	function check(PC $pc){
        return "SELECT * FROM pc2 WHERE uniqueid='".$pc->getUniqueid()."'";
    }
	
	function checkInsert(PC $pc){
        return "SELECT * FROM pc2 WHERE LOWER(id)=LOWER('".$pc->getId()."')";
    }
	
	function checkEdit(PC $pc){
        return "SELECT * FROM pc2 WHERE LOWER(id)=LOWER('".$pc->getId()."')";
    }

    function update(PC $pc){
      return  "UPDATE pc2 SET district = '".$pc->getDistrict()."',name = '".$pc->getName()."',id = '".$pc->getId()."',latitude = '".$pc->getLatitude()."',longitude = '".$pc->getLongitude()."',Paddy_Procurement = '".$pc->getPaddyProcurement()."',Storage_Point = '".$pc->getStoragePoint()."',active = '".$pc->getActive()."' WHERE uniqueid = '".$pc->getUniqueid()."'";
    }
	
	function updateEdit(PC $pc){
      return  "UPDATE pc2 SET district = '".$pc->getDistrict()."',name = '".$pc->getName()."',latitude = '".$pc->getLatitude()."',longitude = '".$pc->getLongitude()."',Paddy_Procurement = '".$pc->getPaddyProcurement()."',Storage_Point = '".$pc->getStoragePoint()."',active = '".$pc->getActive()."' WHERE id = '".$pc->getId()."'";
    }
}

?>
