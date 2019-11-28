# json
A laravel nova field 

##### Table of Contents   
* [Install](#install)      
* [Usage](#usage)       
* [Nested Usage](#nested-usage)       
* [Last Values](#last-values)       
* [Separated Data](#separated-data)       

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
