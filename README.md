# json
A laravel nova field 

##### Table of Contents   
* [Install](#install)      
* [Usage](#usage)       
* [Nested Usage](#nested-usage)       
* [Action Usage](#action-usage)       
* [Showing / Hiding Fields](#showing-and-hiding-fields)       
* [Last Values](#last-values)       
* [Separated Data](#separated-data)       
* [Fill The Value](#fill-the-value)  
* [Null Values](#null-values) 
* [Auto Casting](#auto-casting)  
* [About Implementation](#about-implementation)    

## Install
```bash
composer require armincms/json
``` 
  
## Usage 
So simple.

``` 

  use Armincms\Json\Json;  
  

  Json::make("ColumnName", [ 
      Select::make(__("Discount Type"), "type")
          ->options([
              'percent' => __('Percent'),
              'amount' => __('Amount'),
          ])->rules('required')->default('percent'),
      Number::make(__("Discount Value"), "value")
          ->rules("min:0")
          ->withMeta([
              'min' => 0
          ]),   
  ]),

```

## Nested Usage 
Storing nested data is very like straight data. just like the following; use the `Json` nested.


```  
  use Armincms\Json\Json;  
  

  Json::make("ColumnName", [ 
      Select::make(__("Discount Type"), "type")
          ->options([
              'percent' => __('Percent'),
              'amount' => __('Amount'),
          ])->rules('required')->default('percent'),
      Number::make(__("Discount Value"), "value")
          ->rules("min:0")
          ->withMeta([
              'min' => 0
          ]),   
      // nested data
      Json::make("discount", [ 
        Select::make(__("Discount Type"), "type")
            ->options([
                'percent' => __('Percent'),
                'amount' => __('Amount'),
            ])->rules('required')->default('percent'),
        Number::make(__("Discount Value"), "value")
            ->rules("min:0")
            ->withMeta([
                'min' => 0
            ]),   
      ]),
  ]),

```

## Action Usage 
It is possible to use the `Json` in the `Action` like follow:



```  
use Armincms\Json\Json;

class UpdateTime extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels; 


    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
      //
    }


    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields()
    {
        return collect([
            /// some fields
            
            Json::make(mb_strtolower($meal), [
                Text::make(__("From"), 'from')->rules('required'),
                Text::make(__("Until"), 'until')->rules('required'),  
                Json::make(mb_strtolower($meal), [
                    Text::make(__("From"), 'from'),
                    Text::make(__("Until"), 'until'),  
                ]),
            ]),

            /// some fields
        ])->map(function($field) {
            return $field instanceof Json ? $field->fields() : [$field];
        })->flatten()->all();
    }
}


```
## Showing And Hiding Fields
you can use the field `show/hide` methods on the JSON field. so this method will be called on each field under the `Json` field.The following example will hide all fields from the `index` view.


```  
  use Armincms\Json\Json;  
  

  Json::make("ColumnName", [ 
       // fields
  ])->hideFromIndex(),

``` 


## Save Last Values 
By default; we clean the last data for store new data. but, it's possible to save the last data. for this, call the `saveHistory`  method on parent `Json` class. this causes us to overwrite the new data without clean the last data. see the follow:


```  
  use Armincms\Json\Json;  
  

  Json::make("ColumnName", [ 
       // fields
  ])->saveHistory(),

``` 

## Separated Data
If you want store fields in one column but show in a separate place; you should make multiple `Json` field by one name.see the following:

```  
  use Armincms\Json\Json;  
  

  Json::make("ColumnName", [ 
       // fields group 1
  ]),

  // other feilds


  Json::make("ColumnName", [ 
       // fields group 2
  ])->saveHistory(),

``` 


* ATTENTION: at this situation, you should use `saveHistory` for next `Json` field. 


## Fill The Value
if you want to store the customized value of the field; you can use the `fillUsing` 
method and return custom value. see the follow:

* `fillUsing` accept three argumnets `$request`, `$attribute`, `$requestAttribute`.

```  
  use Armincms\Json\Json;  
  

  Json::make("ColumnName", [ 
       Number::make(__("Discount Value"), "value")
            ->rules("min:0")
            ->withMeta([
                'min' => 0
            ])->fillUsing(function($request, $attribute, $requestAttribute) {
                if($request->exists($requestAttribute)) { 
                    return $request[$requestAttribute];
                }

                return 1000;
            }), 
  ]),
  
```

## Null Values
If there need to store some values as the `null`; you can use the `nullable` method that works like the Nova nullable. 
By default; nullable has the `true` value which means all values will be stored. But; It's possible to reject the storing of null values via passing the `false` value into the `nullable` method.

## Auto Casting
If not defined JSON casting for the field attribute; we will convert the field Value into JSON.
if you need disable this feature; use the `ignoreCasting` method;

## About Implementation
Maybe there exists a question about how this package works? 

I Should say that; this package doesn't have any corresponds component to the `Vuejs`. 
this package just uses `callback`'s for data storing. so; won't changed any field.

with this implementation, you have access to your original fields without changes.
So; for interacts with other packages or fields, exists `toArray` method to access to defined fields.