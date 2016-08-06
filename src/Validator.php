<?php

namespace TypeRocket;

use TypeRocket\Http\Response;

class Validator
{
    public $errors = [];
    public $passes = [];
    public $options = [];
    public $fields = [];
    public $modelClass = [];

    /**
     * Validator
     *
     * Validate data mapped to fields
     *
     * @param array $options the options and validation handler
     * @param array $fields the fields to be validated
     * @param null $modelClass must be a class of SchemaModel
     */
    public function __construct($options, $fields, $modelClass = null)
    {
        $this->modelClass = $modelClass;
        $this->fields = $fields;
        $this->options = $options;

        $this->mapFieldsToValidation();
    }

    /**
     * Get errors
     *
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Get passes
     *
     * @return array
     */
    public function getPasses()
    {
        return $this->passes;
    }

    /**
     * Check if passes
     *
     * @return bool
     */
    public function passed() {
        return empty($this->errors);
    }

    /**
     * Flash validator errors on next request
     *
     * @param \TypeRocket\Http\Response $response
     */
    public function flashErrors( Response $response )
    {
        $errors = '<ul>';

        foreach ($this->errors as $error ) {
            $errors .= "<li>$error</li>";
        }

        $errors .= '</ul>';

        $response->flashNext($errors, 'error');
    }

    /**
     * Map fields to validators
     *
     * @return array
     */
    private function mapFieldsToValidation() {
        foreach ($this->options as $path => $handle) {
            $this->ArrayDots($this->fields, $path, $handle, $path);
        }
    }

    /**
     * Used to format fields
     *
     * @param array $arr
     * @param $path
     * @param $handle
     * @param $fullPath
     *
     * @return array|null
     */
    private function ArrayDots(array &$arr, $path, $handle, $fullPath) {
        $loc = &$arr;
        $dots = explode('.', $path);
        foreach($dots as $step)
        {
            array_shift($dots);
            if($step === '*' && is_array($loc)) {
                $new_loc = &$loc;
                $indies = array_keys($new_loc);
                foreach($indies as $index) {
                    if(isset($new_loc[$index])) {
                        $newFullPath = preg_replace('(\*)', "{$index}", $fullPath, 1);
                        $this->ArrayDots($new_loc[$index], implode('.', $dots), $handle, $newFullPath);
                    }
                }
            } elseif( isset($loc[$step] ) ) {
                $loc = &$loc[$step];
            } else {
                return null;
            }

        }

        if(!isset($indies)) {
            if( !empty($handle) ) {
                $this->validateField( $handle, $loc, $fullPath );
            }
        }

        return $loc;
    }

    /**
     * Validate the Field
     *
     * @param $handle
     * @param $value
     * @param $name
     */
    private function validateField( $handle, $value, $name ) {
        $list = explode('|', $handle);
        foreach( $list as $validation) {
            list( $type, $option, $option2 ) = array_pad(explode(':', $validation, 3), 3, null);
            $field_name = '"<strong>' . ucwords(preg_replace('/\_|\./', ' ', $name)) . '</strong>"';
            switch($type) {
                case 'required' :
                    if( empty( $value ) ) {
                        $this->errors[$name] =  $field_name . ' is required.';
                    } else {
                        $this->passes[$name] = $value;
                    }
                    break;
                case 'length' :
                    if( mb_strlen($value) < $option ) {
                        $this->errors[$name] =  $field_name . " must be at least $option characters long.";
                    } else {
                        $this->passes[$name] = $value;
                    }
                    break;
                case 'email' :
                    if( ! filter_var($value, FILTER_VALIDATE_EMAIL) ) {
                        $this->errors[$name] =  $field_name . " must be at an email address.";
                    } else {
                        $this->passes[$name] = $value;
                    }
                    break;
                case 'numeric' :
                    if( ! is_numeric($value) ) {
                        $this->errors[$name] =  $field_name . " must be a numeric value.";
                    } else {
                        $this->passes[$name] = $value;
                    }
                    break;
                case 'url' :
                    if( ! filter_var($value, FILTER_VALIDATE_URL) ) {
                        $this->errors[$name] =  $field_name . " must be at a URL.";
                    } else {
                        $this->passes[$name] = $value;
                    }
                    break;
                case 'unique' :
                    if( $this->modelClass ) {
                        /** @var \TypeRocket\Models\SchemaModel $model */
                        $model = new $this->modelClass;
                        $model->where($option, $value);

                        if($option2) {
                            $model->where($model->idColumn, '!=', $option2);
                        }

                        $result = $model->first();
                        if($result) {
                            $this->errors[$name] =  $field_name . ' is taken.';
                        } else {
                            $this->passes[$name] = $value;
                        }
                    }
                    break;
            }
        }
    }
}