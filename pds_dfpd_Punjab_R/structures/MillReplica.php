<?php

class MillReplica {
    public $district;
    public $to_district;
    public $name;
    public $id;
    public $type;
    public $latitude;
    public $longitude;
    public $incoming_min_mota;
    public $incoming_min_patla;
    public $incoming_min_saran;
    public $outgoing_min_mota;
    public $outgoing_min_patla;
    public $outgoing_min_saran;
    public $milling_capacity;
    public $milling_capacity1;
    public $milling_capacity2;
    public $uniqueid;
    public $active;

    // Getter methods

    public function getDistrict() {
        return $this->district;
    }

    public function getToDistrict() {
        return $this->to_district;
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

    public function getIncomingMinMota() {
        return $this->incoming_min_mota;
    }

    public function getIncomingMinPatla() {
        return $this->incoming_min_patla;
    }

    public function getIncomingMinSaran() {
        return $this->incoming_min_saran;
    }

    public function getOutgoingMinMota() {
        return $this->outgoing_min_mota;
    }

    public function getOutgoingMinPatla() {
        return $this->outgoing_min_patla;
    }

    public function getOutgoingMinSaran() {
        return $this->outgoing_min_saran;
    }

    public function getMillingCapacity() {
        return $this->milling_capacity;
    }

    public function getMillingCapacity1() {
        return $this->milling_capacity1;
    }

    public function getMillingCapacity2() {
        return $this->milling_capacity2;
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

    public function setToDistrict($to_district) {
        $this->to_district = $to_district;
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

    public function setIncomingMinMota($incoming_min_mota) {
        $this->incoming_min_mota = $incoming_min_mota;
    }

    public function setIncomingMinPatla($incoming_min_patla) {
        $this->incoming_min_patla = $incoming_min_patla;
    }

    public function setIncomingMinSaran($incoming_min_saran) {
        $this->incoming_min_saran = $incoming_min_saran;
    }

    public function setOutgoingMinMota($outgoing_min_mota) {
        $this->outgoing_min_mota = $outgoing_min_mota;
    }

    public function setOutgoingMinPatla($outgoing_min_patla) {
        $this->outgoing_min_patla = $outgoing_min_patla;
    }

    public function setOutgoingMinSaran($outgoing_min_saran) {
        $this->outgoing_min_saran = $outgoing_min_saran;
    }

    public function setMillingCapacity($milling_capacity) {
        $this->milling_capacity = $milling_capacity;
    }

    public function setMillingCapacity1($milling_capacity1) {
        $this->milling_capacity1 = $milling_capacity1;
    }

    public function setMillingCapacity2($milling_capacity2) {
        $this->milling_capacity2 = $milling_capacity2;
    }

    public function setUniqueid($uniqueid) {
        $this->uniqueid = $uniqueid;
    }

    public function setActive($active) {
        $this->active = $active;
    }

    function insert(MillReplica $mill_replica){
        return "INSERT INTO mill_replica (district, to_district, name, id, type, latitude, longitude, incoming_min_mota, incoming_min_patla, incoming_min_saran, outgoing_min_mota, outgoing_min_patla, outgoing_min_saran, milling_capacity, milling_capacity1, milling_capacity2, uniqueid, active) VALUES ('".$mill_replica->getDistrict()."','".$mill_replica->getToDistrict()."','".$mill_replica->getName()."','".$mill_replica->getId()."','".$mill_replica->getType()."','".$mill_replica->getLatitude()."','".$mill_replica->getLongitude()."','".$mill_replica->getIncomingMinMota()."','".$mill_replica->getIncomingMinPatla()."','".$mill_replica->getIncomingMinSaran()."','".$mill_replica->getOutgoingMinMota()."','".$mill_replica->getOutgoingMinPatla()."','".$mill_replica->getOutgoingMinSaran()."','".$mill_replica->getMillingCapacity()."','".$mill_replica->getMillingCapacity1()."','".$mill_replica->getMillingCapacity2()."','".$mill_replica->getUniqueid()."','".$mill_replica->getActive()."')";
    }

    function delete(MillReplica $mill_replica){
        return "DELETE FROM mill_replica WHERE uniqueid='".$mill_replica->getUniqueid()."'";
    }

    function deleteall(MillReplica $mill_replica){
        return "DELETE FROM mill_replica WHERE 1";
    }

    function logname(MillReplica $mill_replica){
        return "SELECT name FROM mill_replica WHERE uniqueid='".$mill_replica->getUniqueid()."'";
    }

    function check(MillReplica $mill_replica){
        return "SELECT * FROM mill_replica WHERE uniqueid='".$mill_replica->getUniqueid()."'";
    }

    function checkInsert(MillReplica $mill_replica){
        return "SELECT * FROM mill_replica WHERE LOWER(id)=LOWER('".$mill_replica->getId()."')";
    }

    function checkEdit(MillReplica $mill_replica){
        return "SELECT * FROM mill_replica WHERE LOWER(id)=LOWER('".$mill_replica->getId()."')";
    }

    function update(MillReplica $mill_replica){
        return "UPDATE mill_replica SET district = '".$mill_replica->getDistrict()."',to_district = '".$mill_replica->getToDistrict()."',name = '".$mill_replica->getName()."',id = '".$mill_replica->getId()."',type = '".$mill_replica->getType()."',latitude = '".$mill_replica->getLatitude()."',longitude = '".$mill_replica->getLongitude()."',incoming_min_mota = '".$mill_replica->getIncomingMinMota()."',incoming_min_patla = '".$mill_replica->getIncomingMinPatla()."',incoming_min_saran = '".$mill_replica->getIncomingMinSaran()."',outgoing_min_mota = '".$mill_replica->getOutgoingMinMota()."',outgoing_min_patla = '".$mill_replica->getOutgoingMinPatla()."',outgoing_min_saran = '".$mill_replica->getOutgoingMinSaran()."',milling_capacity = '".$mill_replica->getMillingCapacity()."',milling_capacity1 = '".$mill_replica->getMillingCapacity1()."',milling_capacity2 = '".$mill_replica->getMillingCapacity2()."',active = '".$mill_replica->getActive()."' WHERE uniqueid = '".$mill_replica->getUniqueid()."'";
    }

    function updateEdit(MillReplica $mill_replica){
        return "UPDATE mill_replica SET district = '".$mill_replica->getDistrict()."',to_district = '".$mill_replica->getToDistrict()."',name = '".$mill_replica->getName()."',type = '".$mill_replica->getType()."',latitude = '".$mill_replica->getLatitude()."',longitude = '".$mill_replica->getLongitude()."',incoming_min_mota = '".$mill_replica->getIncomingMinMota()."',incoming_min_patla = '".$mill_replica->getIncomingMinPatla()."',incoming_min_saran = '".$mill_replica->getIncomingMinSaran()."',outgoing_min_mota = '".$mill_replica->getOutgoingMinMota()."',outgoing_min_patla = '".$mill_replica->getOutgoingMinPatla()."',outgoing_min_saran = '".$mill_replica->getOutgoingMinSaran()."',milling_capacity = '".$mill_replica->getMillingCapacity()."',milling_capacity1 = '".$mill_replica->getMillingCapacity1()."',milling_capacity2 = '".$mill_replica->getMillingCapacity2()."',active = '".$mill_replica->getActive()."' WHERE id = '".$mill_replica->getId()."'";
    }
}

?>
