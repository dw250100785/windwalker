<?php
/**
 * Part of Windwalker project. 
 *
 * @copyright  Copyright (C) 2014 - 2015 LYRASOFT. All rights reserved.
 * @license    GNU Lesser General Public License version 3 or later.
 */

namespace Windwalker\Form\Field;

use Windwalker\Dom\HtmlElement;
use Windwalker\Dom\SimpleXml\XmlHelper;
use Windwalker\Form\Filter\FilterInterface;
use Windwalker\Form\FilterHelper;
use Windwalker\Form\Form;
use Windwalker\Form\Validate\ValidateResult;
use Windwalker\Form\ValidatorHelper;
use Windwalker\Validator\ValidatorInterface;

/**
 * The AbstractField class.
 * 
 * @since  2.0
 */
abstract class AbstractField
{
	/**
	 * Property type.
	 *
	 * @var  string
	 */
	protected $type = '';

	/**
	 * Property element.
	 *
	 * @var  string
	 */
	protected $element = 'input';

	/**
	 * Property name.
	 *
	 * @var  string
	 */
	protected $name = null;

	/**
	 * Property fieldName.
	 *
	 * @var  string
	 */
	protected $fieldName = null;

	/**
	 * Property group.
	 *
	 * @var  string
	 */
	protected $group = null;

	/**
	 * Property fieldset.
	 *
	 * @var  string
	 */
	protected $fieldset = null;

	/**
	 * Property control.
	 *
	 * @var  string
	 */
	protected $control = null;

	/**
	 * Property label.
	 *
	 * @var string
	 */
	protected $label;

	/**
	 * Property value.
	 *
	 * @var  mixed
	 */
	protected $value = null;

	/**
	 * Property attributes.
	 *
	 * @var  string[]
	 */
	protected $attributes = array();

	/**
	 * Property required.
	 *
	 * @var  boolean
	 */
	protected $required = false;

	/**
	 * Property $validator.
	 *
	 * @var  string|ValidatorInterface
	 */
	protected $validator = null;

	/**
	 * Property filter.
	 *
	 * @var  string|FilterInterface|callable
	 */
	protected $filter = null;

	/**
	 * Property attrs.
	 *
	 * @var  array
	 */
	protected $attrs = array();

	/**
	 * The value of false.
	 *
	 * @var  array
	 */
	protected $falseValue = array(
		'disabled',
		'false',
		'null',
		'0',
		'no',
		'none'
	);

	/**
	 * The value of true.
	 *
	 * @var  array
	 */
	protected $trueValue = array(
		'true',
		'yes',
		'1'
	);

	/**
	 * Property form.
	 *
	 * @var  Form
	 */
	protected $form;

	/**
	 * Constructor.
	 *
	 * @param string $name
	 * @param string $label
	 * @param array  $attributes
	 * @param string $filter
	 * @param string $validator
	 */
	public function __construct($name = null, $label = null, $attributes = array(), $filter = null, $validator = null)
	{
		if ($name instanceof \SimpleXMLElement)
		{
			$this->handleXml($name);
		}
		else
		{
			$this->name = $name;
			$this->label = $label;

			$this->attributes = $attributes;
		}

		$this->filter = $filter ? : $this->getAttribute('filter');

		$this->validator = $validator ? : $this->getAttribute('validator');

		$this->required = $this->getBool('required', false);
	}

	/**
	 * getInput
	 *
	 * @return  string
	 */
	public function renderInput()
	{
		$attrs = $this->prepareAttributes();

		if ($this->form && $this->form->getRenderer())
		{
			return $this->form->getRenderer()->renderInput($this, $attrs);
		}

		return $this->buildInput($attrs);
	}

	/**
	 * buildInput
	 *
	 * @param array $attrs
	 *
	 * @return  mixed
	 */
	public function buildInput($attrs)
	{
		return new HtmlElement($this->element, null, $attrs);
	}

	/**
	 * prepareRenderInput
	 *
	 * @param array $attrs
	 *
	 * @return  array
	 */
	abstract public function prepare(&$attrs);

	/**
	 * prepareAttributes
	 *
	 * @return  array
	 */
	public function prepareAttributes()
	{
		$attrs = array();

		$this->prepare($attrs);

		return $attrs;
	}

	/**
	 * getLabel
	 *
	 * @return  string
	 */
	public function renderLabel()
	{
		$attrs['id']    = $this->getAttribute('labelId', $this->getId() . '-label');
		$attrs['class'] = $this->getAttribute('labelClass');
		$attrs['for']   = $this->getAttribute('for', $this->getId());
		$attrs['title'] = $this->getAttribute('description');

		if ($this->form && $this->form->getRenderer())
		{
			return $this->form->getRenderer()->renderLabel($this, $attrs);
		}

		$label = $this->getLabel();

		if ($this->required)
		{
			$label = '<span class="windwalker-input-required-hint">*</span> ' . $label;
		}

		return (string) new HtmlElement('label', $label, $attrs);
	}

	/**
	 * renderView
	 *
	 * @return  string
	 */
	public function renderView()
	{
		return $this->value;
	}

	/**
	 * render
	 *
	 * @return  string
	 */
	public function render()
	{
		$attrs['id'] = $this->getAttribute('controlId', $this->getId() . '-control');
		$attrs['class'] = $this->type . '-field ' . $this->getAttribute('controlClass');

		if ($this->form && $this->form->getRenderer())
		{
			return $this->form->getRenderer()->renderField($this, $attrs);
		}

		$label = $this->renderLabel();
		$input = $this->renderInput();

		return (string) new HtmlElement('div', $label . $input, $attrs);
	}

	/**
	 * getLabel
	 *
	 * @return  mixed
	 */
	public function getLabel()
	{
		return $this->label;
	}

	/**
	 * getId
	 *
	 * @return  string
	 */
	public function getId()
	{
		$control = $this->control ? $this->control . '/' : '';

		return 'input-' . preg_replace('/[^A-Z0-9_]+/i', '-', $control . $this->getName(true));
	}

	/**
	 * validate
	 *
	 * @return  ValidateResult
	 */
	public function validate()
	{
		$result = new ValidateResult;

		if ($this->required && !$this->checkRequired())
		{
			return $result->setMessage(sprintf('Field %s value not allow empty.', $this->getLabel()))
				->setResult(ValidateResult::STATUS_REQUIRED)
				->setField($this);
		}

		if ($this->value !== null && $this->value !== '' && $this->validator && !$this->checkRule())
		{
			return $result->setMessage(sprintf('Field %s validate fail.', $this->getLabel()))
				->setResult(ValidateResult::STATUS_FAILURE)
				->setField($this);
		}

		return $result;
	}

	/**
	 * checkRequired
	 *
	 * @return  mixed
	 */
	public function checkRequired()
	{
		$value = (string) $this->value;

		if ($this->value || $value === '0')
		{
			return true;
		}

		return false;
	}

	/**
	 * checkRule
	 *
	 * @return  mixed
	 */
	public function checkRule()
	{
		return $this->getValidator()->validate($this->value);
	}

	/**
	 * filter
	 *
	 * @return  static
	 */
	public function filter()
	{
		$filter = $this->getFilter();

		if (is_callable($filter))
		{
			$this->value = call_user_func($filter, $this->value);
		}
		else
		{
			$this->value = $filter->clean($this->value);
		}

		return $this;
	}

	/**
	 * prepareStore
	 *
	 * @return  void
	 */
	public function prepareStore()
	{
	}

	/**
	 * Method to get property Name
	 *
	 * @param bool $withGroup
	 *
	 * @return  string
	 */
	public function getName($withGroup = false)
	{
		$group = $withGroup ? $this->getGroup() : '';

		$group = $group ? $group . '/' : '';

		return $group . $this->name;
	}

	/**
	 * Method to set property name
	 *
	 * @param   null $name
	 *
	 * @return  static  Return self to support chaining.
	 */
	public function setName($name)
	{
		$this->fieldName = null;

		$this->name = $name;

		return $this;
	}

	/**
	 * Method to get property FieldName
	 *
	 * @param bool $refresh
	 *
	 * @return  string
	 */
	public function getFieldName($refresh = false)
	{
		if (!$this->fieldName || $refresh)
		{
			// Prevent '..'
			$names = array_values(array_filter(explode('/', $this->getName(true)), 'strlen'));

			$control = $this->control ? $this->control : array_shift($names);

			$names = array_map(
				function ($value)
				{
					return '[' . $value . ']';
				},
				$names
			);

			$this->fieldName = $control . implode('', $names);
		}

		return $this->fieldName;
	}

	/**
	 * Method to set property fieldName
	 *
	 * @param   null $fieldName
	 *
	 * @return  static  Return self to support chaining.
	 */
	public function setFieldName($fieldName)
	{
		$this->fieldName = $fieldName;

		return $this;
	}

	/**
	 * Method to get property Group
	 *
	 * @return  null
	 */
	public function getGroup()
	{
		return $this->group;
	}

	/**
	 * Method to set property group
	 *
	 * @param   null $group
	 *
	 * @return  static  Return self to support chaining.
	 */
	public function setGroup($group)
	{
		$this->fieldName = null;

		$this->group = $group;

		return $this;
	}

	/**
	 * Method to get property Fieldset
	 *
	 * @return  null
	 */
	public function getFieldset()
	{
		return $this->fieldset;
	}

	/**
	 * Method to set property fieldset
	 *
	 * @param   null $fieldset
	 *
	 * @return  static  Return self to support chaining.
	 */
	public function setFieldset($fieldset)
	{
		$this->fieldset = $fieldset;

		return $this;
	}

	/**
	 * Method to get property Value
	 *
	 * @return  null
	 */
	public function getValue()
	{
		return ($this->value !== null && $this->value !== '') ? $this->value : $this->getAttribute('default');
	}

	/**
	 * Method to set property value
	 *
	 * @param   null $value
	 *
	 * @return  static  Return self to support chaining.
	 */
	public function setValue($value)
	{
		$this->value = $value;

		return $this;
	}

	/**
	 * description
	 *
	 * @param   string  $desc
	 *
	 * @return  static
	 */
	public function description($desc)
	{
		$this->setAttribute('description', $desc);

		return $this;
	}

	/**
	 * defaultValue
	 *
	 * @param   string  $value
	 *
	 * @return  static
	 */
	public function defaultValue($value)
	{
		$this->setAttribute('default', $value);

		return $this;
	}

	/**
	 * getDefaultValue
	 *
	 * @return  mixed
	 */
	public function getDefaultValue()
	{
		return $this->getAttribute('default');
	}

	/**
	 * Method to set property rule
	 *
	 * @param   string|ValidatorInterface $validator
	 *
	 * @return  static  Return self to support chaining.
	 */
	public function setValidator($validator)
	{
		$this->validator = $validator;

		return $this;
	}

	/**
	 * Method to get property Rule
	 *
	 * @return  ValidatorInterface
	 */
	public function getValidator()
	{
		if (!($this->validator instanceof ValidatorInterface))
		{
			$this->validator = ValidatorHelper::create($this->validator);
		}

		return $this->validator;
	}

	/**
	 * Method to set property filter
	 *
	 * @param   string|FilterInterface|callable $filter
	 *
	 * @return  static  Return self to support chaining.
	 */
	public function setFilter($filter)
	{
		$this->filter = $filter;

		return $this;
	}

	/**
	 * Method to get property Filter
	 *
	 * @return  string|FilterInterface|callable
	 */
	public function getFilter()
	{
		if (!($this->filter instanceof FilterInterface) && !is_callable($this->filter))
		{
			$this->filter = FilterHelper::create($this->filter);
		}

		return $this->filter;
	}

	/**
	 * handleXml
	 *
	 * @param \SimpleXMLElement $xml
	 *
	 * @return  void
	 */
	protected function handleXml(\SimpleXMLElement $xml)
	{
		$this->name = XmlHelper::get($xml, 'name');
		$this->label = XmlHelper::get($xml, 'label');

		$this->attributes = XmlHelper::getAttributes($xml);

		$form = $xml;

		$group = array();

		while ($parent = $form->xpath('..'))
		{
			$parent = $parent[0];

			$name = $parent->getName();

			if ($name == 'fieldset')
			{
				$this->fieldset = $this->fieldset ? : (string) $parent['name'];
			}
			elseif ($name == 'group')
			{
				array_unshift($group, (string) $parent['name']);
			}

			$form = $parent;
		}

		$this->group = implode('.', $group);
	}

	/**
	 * Method to get property Control
	 *
	 * @return  string
	 */
	public function getControl()
	{
		return $this->control;
	}

	/**
	 * Method to set property control
	 *
	 * @param   string $control
	 *
	 * @return  static  Return self to support chaining.
	 */
	public function setControl($control)
	{
		$this->fieldName = null;

		$this->control = $control;

		return $this;
	}

	/**
	 * label
	 *
	 * @param string $label
	 *
	 * @return  static
	 */
	public function label($label)
	{
		$this->label = $label;

		return $this;
	}

	/**
	 * required
	 *
	 * @param bool $value
	 *
	 * @return  static
	 */
	public function required($value = true)
	{
		$this->setAttribute('required', $value);

		$this->required = $value;

		return $this;
	}

	/**
	 * disabled
	 *
	 * @param bool $value
	 *
	 * @return  static
	 */
	public function disabled($value = true)
	{
		$this->setAttribute('disabled', $value);

		return $this;
	}

	/**
	 * readonly
	 *
	 * @param bool $value
	 *
	 * @return  static
	 */
	public function readonly($value = true)
	{
		$this->setAttribute('readonly', $value);

		return $this;
	}

	/**
	 * class
	 *
	 * @param   string  $value
	 *
	 * @return  static
	 */
	public function setClass($value)
	{
		$this->setAttribute('class', $value);

		return $this;
	}

	/**
	 * labelClass
	 *
	 * @param   string  $value
	 *
	 * @return  static
	 */
	public function labelClass($value)
	{
		$this->setAttribute('labelClass', $value);

		return $this;
	}

	/**
	 * getAttribute
	 *
	 * @param string $name
	 * @param mixed  $default
	 *
	 * @return  mixed
	 */
	public function getAttribute($name, $default = null)
	{
		return isset($this->attributes[$name]) ? $this->attributes[$name] : $default;
	}

	/**
	 * getAttribute
	 *
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return  static
	 */
	public function setAttribute($name, $value)
	{
		$this->attributes[$name] = $value;

		return $this;
	}


	/**
	 * Get attribute. Alias of `getAttribute()`.
	 *
	 * @param string  $attr    The attribute name.
	 * @param mixed   $default The default value.
	 *
	 * @return mixed The return value of this attribute.
	 */
	public function get($attr, $default = null)
	{
		return $this->getAttribute($attr, $default);
	}

	/**
	 * set
	 *
	 * @param string $attr
	 * @param mixed  $value
	 *
	 * @return  static
	 */
	public function set($attr, $value)
	{
		$this->setAttribute($attr, $value);

		return $this;
	}

	/**
	 * append
	 *
	 * @param string $attr
	 * @param string $value
	 *
	 * @return  static
	 */
	public function appendAttribute($attr, $value)
	{
		$this->setAttribute($attr, trim($this->getAttribute($attr) . $value));

		return $this;
	}

	/**
	 * prependAttribute
	 *
	 * @param string $attr
	 * @param string $value
	 *
	 * @return  static
	 */
	public function prependAttribute($attr, $value)
	{
		$this->setAttribute($attr, trim($value . $this->getAttribute($attr)));

		return $this;
	}

	/**
	 * Method to convert some string like `true`, `1`, `yes` to boolean TRUE,
	 * and `no`, `false`, `disabled`, `null`, `none`, `0` string to boolean FALSE.
	 *
	 * @param string  $attr    The attribute name.
	 * @param mixed   $default The default value.
	 *
	 * @return mixed The return value of this attribute.
	 */
	public function getBool($attr, $default = null)
	{
		$value = $this->getAttribute($attr, $default);

		if (in_array((string) $value, $this->falseValue) || !$value)
		{
			return false;
		}

		return true;
	}

	/**
	 * Just an alias of `getBool()` but FALSE will return TRUE.
	 *
	 * @param string  $attr    The attribute name.
	 * @param mixed   $default The default value.
	 *
	 * @return mixed The return value of this attribute.
	 */
	public function getFalse($attr, $default = null)
	{
		return !$this->getBool($attr, $default);
	}

	/**
	 * Get all attributes.
	 *
	 * @return  array The return values of all attributes.
	 */
	public function getAttributes()
	{
		return $this->attributes;
	}

	/**
	 * If this attribute not exists, use this value as default, or we use original value from xml.
	 *
	 * @param string            $attr    The attribute name.
	 * @param string            $value   The value to set as default.
	 *
	 * @return  void
	 */
	public function def($attr, $value)
	{
		$this->attributes[$attr] = isset($this->attributes[$attr]) ? $this->attributes[$attr] : (string) $value;
	}

	/**
	 * Method to get property Type
	 *
	 * @return  string
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * Method to get property Form
	 *
	 * @return  Form
	 */
	public function getForm()
	{
		return $this->form;
	}

	/**
	 * Method to set property form
	 *
	 * @param   Form $form
	 *
	 * @return  static  Return self to support chaining.
	 */
	public function setForm($form)
	{
		$this->form = $form;

		return $this;
	}

	/**
	 * Escape html string.
	 *
	 * @param   string  $text
	 *
	 * @return  string
	 *
	 * @since  2.1.9
	 */
	public function escape($text)
	{
		return htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
	}
}
