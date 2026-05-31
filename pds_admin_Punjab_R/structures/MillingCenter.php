<?php

class MillingCenter{
  public $Id;
  public $Name;

    /**
     * Get the value of Id
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->Id;
    }

    /**
     * Set the value of Id
     *
     * @param mixed $Id
     *
     * @return self
     */
    public function setId($Id)
    {
        $this->Id = $Id;

        return $this;
    }

    /**
     * Get the value of name
     *
     * @return mixed
     */
    public function getName()
    {
        return $this->Name;
    }

    /**
     * Set the value of name
     *
     * @param mixed $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->Name = $name;

        return $this;
    }


    function insert(MillingCenter $MillingCenter){

        return "INSERT INTO milling_center(id,name) VALUES ('".$MillingCenter->getId()."','".$MillingCenter->getName()."')";

    }
	
	function check(MillingCenter $MillingCenter){

        return "SELECT * FROM milling_center WHERE name='".$MillingCenter->getName()."'";

    }

    function delete(MillingCenter $MillingCenter){

        return "DELETE FROM milling_center WHERE id='".$MillingCenter->getId()."'";

    }
	
	function logname(MillingCenter $MillingCenter){

        return "SELECT name FROM milling_center WHERE id='".$MillingCenter->getId()."'";

    }

    function update(MillingCenter $MillingCenter){

      return  "UPDATE milling_center SET name='".$MillingCenter->getName()."' WHERE id = '".$MillingCenter->getId()."'";

    }

}

 ?>
