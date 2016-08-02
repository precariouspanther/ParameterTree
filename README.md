# PHP ParameterTree

ParameterTree is a multi-dimensional, namespaced parameter bag simplifying access to multidimensional arrays 
(such as configs, GET/POST/SESSION params, etc), with default return values and typecasting.

## Basic Usage

```php
$dbConfig = new ParameterTree(
    [   
        "master"=>[
            "host"=>"db.com",
            "user"=>"DB-DUDE",
            "pass"=>"super-secret123",
            "port"=>"3306"
        ]
    ]
);
echo $dbConfig->get("master.host","localhost"); //db.com
echo $dbConfig->get("slave.host","localhost"); //localhost
var_dump($dbConfig->hasKey("master.host")); //true
var_dump($dbConfig->hasKey("master.soup")); //false
$dbConfig->set("slave.pass","abc123");
var_dump($config->toArray()); // ["master"=>["host"=>"db.com","user"=>"DB-DUDE","pass"=>"super-secret123","port"=>"3306],"slave"=>["pass"=>"abc123"]]
```



## License

ParameterTree is open-sourced software licensed under the MIT license
