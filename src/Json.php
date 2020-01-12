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
    public function mergeValue($request, $model, $attribute, $requestAttribute) : array
    { 
        $key    = str_replace("{$this->separator}", '.', $attribute);
        $value  = $this->fetchValueFromRequest($request, $attribute, $requestAttribute);
        $old    = [$this->name => collect($model->{$this->name})->toArray()];

        return data_set($old, $key, $value)[$this->name];
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
     * Serialize attribute for storing.
     * 
     * @param  mixed $value 
     * @param  \Illuminate\Database\Eloquent\Model $model 
     * @return array|string        
     */
    public function serializeValue($value, $model)
    {   
        return $this->isJsonCastable($model) ? $value : json_encode($value);  
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
     * Convert Json to array of fields.
     *
     * @return array
     */
    public function toArray() : array
    {
        return (array) $this->fields();
    }
}
