About PrestaShop Shop Generator
--------

The shop generator generates a list of folders & xml files into 'generated_data' dir which should be copied in the 
/install/fixtures/fashion directory of PrestaShop, to generate at the installation a shop initialized with the specified 
number of entities.


Installation & configuration
--------

To setup the configuration of the module, just run
```
composer install
```
This will generates a configuration file in app/config/config.yml


How to run the script
--------
```
php app/console.php
```
Make sure to have enough memory allocated to php, as it could eat a lot of memory depending on the number of entities
you want to generate


Entity Model syntax
--------
Each entity model is described in the src/Model directory.

If you want to add a new Model, create of file with the same name the class it's related to, and an entry in the 
app/config/config.yml.dist file (the name should be the pluralized & tablized version of the model name).


The model file is in yml format, and contains three main section:

1. <b>The fields section (required)</b>

    This section describes the list of fields of an entity (not language related)
    Available options in this section:
    
    1. <i><b>columns (required) </b></i>

        Describe each field of the entity we want to generate.
        
        Syntax:
        ```yaml
        columns:
            id:
              type: increment        
            id_state:
              relation: State
            exclusive_fields:
              id_customer:
                relation: Customer
              id_manufacturer:
                relation: Manufacturer
              id_supplier:
                relation: Supplier
            id_warehouse:
              value: 0
            alias:
              type: words
              args:
                - 10 #nb words
            name:
              type: word
              args:
                - 10 #nb chars
              hidden: true   
            price:
              type: numberBetween
              args:
                - 1 #start
                - 1000 #stop
            wholesale_price:
              value: '{price}/100'              
        ```
        1. <u>the 'type' property</u>
            
            This properly allows to generate random value. 'increment' is a simple autoincrement, another types
            available are described from the faker module: https://github.com/fzaninotto/Faker 
            
            If you need to pass an argument to a faker function, just add the 'args:' tag like in the above example.
            
            If you want to generate a field, but hide it from the final result, add the "hidden: true" property
            (only useful if the field in question is referenced as an "id", but only present in the field_lang)
            
        2. <u>the 'relation' property</u>
        
            The 'relation' property indicates it should generates the value from an another entity (it will use a value
            from the 'id' of the other entity)       
            
        3. <u>the 'value' property</u>
                         
            The 'value' property sets a specific value for the column. It could also be a reference to another
            column, or a mathematical expression, like the "wholesale_price" in the example above.
            
        4. <u>the 'exclusive_fields' property</u>
                 
            Some columns should have a value only if other column are not set.
            That's the purpose of this property. In the example above only one randomly chosen field 
            among id_customer/id_manufacturer/id_supplier will be set.
    
    2. <i><b>class (optional) </b></i>
    
        The name of the class related to the entity.
        
        Example: 
            
        ```yaml
        class: 'Carrier'
        ```
    3. <i><b>sql (optional)</b></i>
        
        Sql argument when want to add to help debugging
        
        Example:
        ```yaml
        sql: 'a.id_carrier > 1'
        ```
    
    4. <i><b>id</b></i>
    
        The 'id' tag sets which field inside the 'columns' property should be considered as a the reference unique field 
        for relation resolution.
        
        Example:
        ```yaml
        id: 'name'
        ```
     
    5. <i><b>primary</b></i>
    
        When the primary tag is used, the script iterate over all the existing values (the fields in the 'primary' tag
        should be described as relations to other entities)
        
        Example:
        ```yaml
        primary: 'id_carrier, id_group'
        columns:
            id_carrier:
              relation: Carrier
            id_group:
              relation: Group
       ```      
     
    6. <i><b>image (optional)</b></i>
        
        Generate random images in the given relative path of the generated_data/img/ directory for each entity.
        It's used in conjonction with image_width, image_height and image_category.
        
        Example:
        ```yaml
       image: 'c'
       image_width: 141
       image_height: 180
       image_category: abstract
        ```
           
        Possible image_category are:
        ```  
        abstract
        animals
        business
        cats
        city
        food
        night
        life
        fashion
        people
        nature
        sports
        technics 
        transport
        ```                                        

2. <b>The fields_lang section (optional)</b>

    This section describes the list of fields present in the language related part of the entity (if any)
    You can set an optional 'id_shop' tag and a 'columns' property which support the type same 'value' and 'type' than the 
    'fields' section.
    
    Example:
    ```yaml
    fields_lang:
        id_shop: 1
        columns:
            name:
              type: words
              args:
                - 6
            description:
              type: sentence
            description_short:
              type: sentence
              args:
                - 4
            link_rewrite:
              type: slug
            available_now:
              value: In stock
    ```  

3. <b>The entities section (optional)</b>

    This section describes any custom entities we want to create (no random generation for those one)
    The key of each entry used will be used as the 'id' of the entity

    Example:
    ```yaml
    entities:
        My_carrier:
            fields:
                id_reference: 2
                active: 1
                shipping_handling: 1
                range_behaviour: 0
                is_free: 0
                shipping_external: 0
                need_range: 0
                shipping_method: 0
                max_width: 0
                max_height: 0
                max_depth: 0
                max_weight: 0
                grade: 0
                name: My carrier
                url: ~
            fields_lang:
                delay: Delivery next day!
    ```


Default xml data
--------
If you want to use a default xml file instead of generating one using the entity model, just put it in the default_data 
directory.
It will be automatically parsed by the script and will be taken into account for the existing entity relations.


TODO
--------
Speed the image generation by using a local image repository