<?php

namespace WPMVC\Framework;

class Model extends \Illuminate\Database\Eloquent\Model
{

	private $_roles=array();
	private $_errors=array();

	public function add_rules_to(\Valitron\Validator $validator)
	{
		return $validator;
	}

	public function validate()
	{
		$validator = new \Valitron\Validator($this->toArray());
		$this->add_rules_to($validator);
		if ($validator->validate())
			return true;
		$this->_errors = $validator->errors();
		return false;
	}

	public function save($options = array())
	{
		if (isset($options['validate']) and !$options['validate'])
			return parent::save($options);
		if (!$this->validate())
			return false;
		return parent::save($options);
	}

	public function get_errors()
	{
		return $this->_errors;
	}

	public function __get($name)
	{
		$getter='get_'.$name;
		if(method_exists($this,$getter))
			return $this->$getter();
		elseif(isset($this->_roles[$name]))
			return $this->_roles[$name];
		elseif(is_array($this->_roles))
			foreach($this->_roles as $object)
				if($object->enabled && (property_exists($object,$name) || $object->can_get_property($name)))
					return $object->$name;
		return parent::__get($name);
	}

	public function __set($name,$value)
	{
		$setter='set_'.$name;
		if(method_exists($this,$setter))
					return $this->$setter($value);
		elseif(is_array($this->_roles))
			foreach($this->_roles as $object)
				if($object->enabled() && (property_exists($object,$name) || $object->can_set_property($name)))
					return $object->$name=$value;
		return parent::__set($name, $value);
	}

	public function __isset($name)
	{
		$getter='get_'.$name;
		if(method_exists($this,$getter))
			return $this->$getter()!==null;
		elseif(is_array($this->_roles))
		{
			if(isset($this->_roles[$name]))
				return true;
			foreach($this->_roles as $object)
				if($object->enabled && (property_exists($object,$name) || $object->can_get_property($name)))
					return $object->$name!==null;
		}
		return parent::__isset($name);
	}

	public function __unset($name)
	{
		$setter='set_'.$name;
		if(method_exists($this,$setter))
			$this->$setter(null);
		elseif(is_array($this->_roles))
		{
			if(isset($this->_roles[$name]))
				$this->remove_role($name);
			else
				foreach($this->_roles as $object)
					if($object->get_enabled())
					{
						if(property_exists($object,$name))
							return $object->$name=null;
						elseif($object->can_set_property($name))
							return $object->$setter(null);
					}
		}
		return parent::__unset($name);
	}

	public function __call($name,$parameters)
	{
		if($this->_roles!==null)
			foreach($this->_roles as $object)
				if($object->enabled && method_exists($object,$name))
					return call_user_func_array(array($object,$name),$parameters);
		if(class_exists('Closure', false) && $this->can_get_property($name) && $this->$name instanceof Closure)
						return call_user_func_array($this->$name, $parameters);
		return parent::__call($name, $parameters);
	}

	public function get_role($role)
	{
		return isset($this->_roles[$role]) ? $this->_roles[$role] : null;
	}

	public function get_roles()
	{
		return $this->_roles;
	}

	public function remove_all_roles()
	{
		$this->_roles = array();
	}

	public function add_role($name, $role)
	{
		if(!($role instanceof \WPMVC\Framework\Role))
			return;
		$role->assign_to($this);
		return $this->_roles[$name]=$role;
	}

	public function remove_role($name)
	{
		if(isset($this->_roles[$name]))
			return;
		$this->_roles[$name]->remove_from($this);
		$role=$this->_roles[$name];
		unset($this->_roles[$name]);
		return $role;
	}

	public function hasProperty($name)
	{
		return method_exists($this,'get_'.$name) || method_exists($this,'set_'.$name);
	}

	public function can_get_property($name)
	{
					return method_exists($this,'get_'.$name);
	}

	public function can_set_property($name)
	{
					return method_exists($this,'set_'.$name);
	}
}
