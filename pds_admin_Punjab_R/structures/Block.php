<?php

class Block {
    public $Id;
    public $Name;

    public function getId() {
        return $this->Id;
    }

    public function setId($Id) {
        $this->Id = $Id;
        return $this;
    }

    public function getName() {
        return $this->Name;
    }

    public function setName($name) {
        $this->Name = $name;
        return $this;
    }

    function insert(Block $Block) {
        return "INSERT INTO block(id,name) VALUES ('".$Block->getId()."','".$Block->getName()."')";
    }

    function check(Block $Block) {
        return "SELECT * FROM block WHERE name='".$Block->getName()."'";
    }

    function delete(Block $Block) {
        return "DELETE FROM block WHERE id='".$Block->getId()."'";
    }

    function logname(Block $Block) {
        return "SELECT name FROM block WHERE id='".$Block->getId()."'";
    }

    function update(Block $Block) {
        return "UPDATE block SET name='".$Block->getName()."' WHERE id='".$Block->getId()."'";
    }
}

?>
