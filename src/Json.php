<?php

namespace Armincms\Json;
 
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Fields\Field;

class Json extends MergeValue
{ 
    /**
     * The name of the json.
     *
     * @var string
     */
    public $name; 

    /**
     * Join separator.
     *
     * @var string
     */
    public $separator = '->'; 

    /**
     * Array of fields callback.
     *
     * @var array
     */
    public $fillCallbacks = [];  

    /**
     * Status of auto casting feature.
     *
     * @var bool
     */
    protected $autoCasting = true;

    /**
     * Field history status.
     *
     * @var bool
     */
    protected $saveHistory = false;

    /**
     * Status of the old values.
     *
     * @var bool
     */
    protected $cleanedOut = false;   

    /**
     * Indicates if the field accepting nullable.
     *
     * @var bool
     */
    public $nullable = true;

    /**
     * Values which will be replaced to null.
     *
     * @var array
     */
    public $nullValues = [''];

    /**
     * Create a new json instance.
     *
     * @param  string  $name
     * @param  \Closure|array  $fields
     * @return void
     */
    public function __construct($name, $fields = [])
    {
        $this->name = $name;

        parent::__construct($this->prepareFields($fields));
    } 

    /**
     * Resolve method for unsing field in action.
     * 
     * @return void
     */
    public function resolve()
    {
        
    }

    /**
     * Get available fields.
     * 
     * @return array
     */
    public function fields()
    {
        return collect($this->data)->map(function($field) {
            return $field instanceof self ? $field->fields() : [$field];
        })->flatten()->all();
    }

    /**
     * Create a new element.
     *
     * @return static
     */
    public static function make(...$arguments)
    {
        return new static(...$arguments);
    }

    /**
     * Force field to save histroy or not.
     * 
     * @param  boolean $saveHistory
     * @return $this
     */
    public function saveHistory($saveHistory = true) : self
    {
        $this->saveHistory = $saveHistory;

        return $this;
    }

    /**
     * Prepare the given fields.
     *
     * @param  \Closure|array  $fields
     * @return array
     */
    protected function prepareFields($fields)
    {
        return collect(is_callable($fields) ? $fields() : $fields)->map(function($field) {
            return $field instanceof self 
                        ? $this->prepareJsonFields($field) 
                        : [$this->prepareField($field)];
        })->flatten()->all(); 
    }  

    /**
     * Prepare the given Json fields.
     *
     * @param  $this $json
     * @return array
     */
    public function prepareJsonFields(self $json)
    { 
        $fields = collect($json->fields())->each(function($field) use ($json) {  
            $field->fillUsing($json->fillCallbacks[$field->attribute] ?? null);
        }); 

        return  $this->prepareFields($fields);
    }

    /**
     * Preapre the field;
     * 
     * @param  \Laravel\Nova\Fields\Field  $field
     * @return \Laravel\Nova\Fields\Field
     */
    public function prepareField(Field $field) : Field
    { 
        return tap($field, function($field) { 
            $field->wrapper = $this->name;
            $field->attribute = "{$this->name}{$this->separator}{$field->attribute}"; 

            $this->fillCallbacks[$field->attribute] = $field->fillCallback;

            $field->fillUsing([$this, 'fillCallback']);
        });  
    }

    /**
     * The callback that should be used to hydrate the model attribute for the field.
     * 
     * @param  \Iluminate\Http\Request $request          
     * @param  \Illuminate\Database\Eloquent\Model $model            
     * @param  string $attribute        
     * @param  string $requestAttribute 
     * @return void                   
     */
    public function fillCallback($request, $model, $attribute, $requestAttribute = null)
    {     
        $this->checkHistory($model);

        $value = $this->mergeValue($request, $model, $attribute, $requestAttribute);  


        $model->{$this->name} = $this->serializeValue($value, $model);
    }   

    /**
     * Check value history.
     * 
     * @param  \Illuminate\Database\Eloquent\Model $model 
     * @return void
     */
    public function checkHistory($model) : self
    { 
        if(! $this->needHistory()) {
            $this->isCleanedOut() || $this->cleanHistory($model);
        }  

        return $this;
    }

    /**
     * Determine if need to save history.
     * 
     * @return bool
     */
    public function needHistory() : bool
    {
        return (bool) $this->saveHistory;
    }

    /**
     * Determine if the value history is cleaned out.
     * 
     * @return bool
     */
    public function isCleanedOut() : bool
    {
        return (bool) $this->cleanedOut;
    }

    /**
     * Clear last values.
     * 
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return $this       
     */
    public function cleanHistory($model) : self
    { 
        $model->{$this->name} = null;

        $this->cleanedOut = true;

        return $this;
    }

    /**
     * Merge current value by last values.
     * 
     * @param  \Iluminate\Http\Request $request          
     * @param  \Illuminate\Database\Eloquent\Model $model            
     * @param  string $attribute        
     * @param  string $requestAttribute 
     * @return array
     */
    protected function mergeValue($request, $model, $attribute, $requestAttribute) : array
    {   
        $value = $this->fetchValueFromRequest($request, $attribute, $requestAttribute); 
        $key   = $this->buildDottedName($attribute); 
        $old   = $this->old($model);

        $data = $this->storable($value) 
                    ? data_set($old, $key, $this->isNullValue($value) ? null : $value)
                    : $old; 
        
        return $data[$this->name];        
    } 

    /**
     * Fetch the attribute value from request.
     * 
     * @param  \Iluminate\Http\Request $request                     
     * @param  string $attribute        
     * @param  string $requestAttribute 
     * @return mixed
     */
    public function fetchValueFromRequest($request, $attribute, $requestAttribute)
    { 
        $retriver = $this->getValueRetriever($attribute);  

        return $retriver($request, $attribute, $requestAttribute); 
    } 

    /**
     * Build dot name from attribute name.
     *                  
     * @param  string $attribute         
     * @return string
     */
    public function buildDottedName(string $attribute) : string
    {
        return str_replace("{$this->separator}", '.', $attribute);
    }

    /**
     * Get the request value retriver.
     * 
     * @param  string $attribute 
     * @return callable            
     */
    public function getValueRetriever($attribute) : callable
    {     
        return $this->fillCallbacks[$attribute] ?? function($request, $attribute, $requestAttribute) { 
            if($request->exists($requestAttribute)) { 
                return $request[$requestAttribute];
            }
        };
    }  

    /**
     * Determine if need to store value.
     * 
     * @param  mixed $value [description]
     * @return bool
     */ 
    protected function storable($value) : bool
    {
        return $this->nullable || ! $this->isNullValue($value);
    } 

    /**
     * Get old stored values.
     * 
     * @param  mixed $value [description]
     * @return bool
     */
    protected function old($model)
    {
        return [$this->name => collect($model->{$this->name})->toArray()];
    }

    /**
     * Indicate that the field should be nullable.
     *
     * @param  bool  $nullable
     * @param  array|Closure  $values
     * @return $this
     */
    public function nullable($nullable = true, $values = null)
    {
        $this->nullable = $nullable;

        if ($values !== null) {
            $this->nullValues($values);
        }

        return $this;
    }

    /**
     * Specify nullable values.
     *
     * @param  array|Closure  $values
     * @return $this
     */
    public function nullValues($values)
    {
        $this->nullValues = $values;

        return $this;
    } 

    /**
     * Check value for null value.
     *
     * @param  mixed $value
     * @return bool
     */
    protected function isNullValue($value)
    { 
        return is_callable($this->nullValues)
            ? ($this->nullValues)($value)
            : in_array($value, (array) $this->nullValues);
    }

    /**
     * Serialize attribute for storing.
     * 
     * @param  mixed $value 
     * @param  \Illuminate\Database\Eloquent\Model $model 
     * @return array|string        
     */
    public function serializeValue($value, $model)
    {   
        return ! $this->autoCasting || $this->isJsonCastable($model) ? $value : json_encode($value);  
    }

    /**
     * Determine whether a value is JSON castable for inbound manipulation.
     *
     * @param  string  $key
     * @return bool
     */
    protected function isJsonCastable($model)
    {
        return $model->hasCast($this->name, ['array', 'json', 'object', 'collection']);
    }  

    /**
     * Receive if need the result as an array.
     *
     * @param  void
     * @return $this
     */
    public function ignoreCasting()
    {
        $this->autoCasting = false;

        return $this;
    }

    /**
     * Convert Json to array of fields.
     *
     * @return array
     */
    public function toArray() : array
    {
        return (array) $this->fields();
    }

    /**
     * Handle field show/hide methods.
     * 
     * @param  string $method     
     * @param  array $parameters 
     * @return $this             
     */
    public function __call($method, $parameters)
    {
        if(! method_exists(\Laravel\Nova\Fields\FieldElement::class, $method)) { 
            throw new \BadMethodCallException;
        }

        foreach ($this->toArray() as $field) {
            call_user_func_array([$field, $method], $parameters);
        }

        return $this; 
    }
    
    /**
     * When the panel attached to the translatable field, we'll attach it to fields.
     * 
     * @param string $key   
     * @param mixed $value 
     */
    public function __set($key, $value)
    {  
        foreach ($this->toArray() as $field) {
            $field->$key = $value;
        }
    }
}
