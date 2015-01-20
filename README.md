# PHP ParameterTree

ParameterTree is a multi-dimensional, namespaced parameter bag simplifying access to multidimensional arrays 
(such as configs, GET/POST/SESSION params, etc), with built in protection to avoid unintentionally overriding entire branches when a key is already used.

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
$dbConfig->set("slave.pass","abc123");
var_dump($config->getArray()); // ["master"=>["host"=>"db.com","user"=>"DB-DUDE","pass"=>"super-secret123","port"=>"3306],"slave"=>["pass"=>"abc123]]
```


```php
$session = new ParameterTree($_SESSION);
echo $session->get('Security.CurrentUser.email'); // john@doe.com
var_dump($session->get('Security.CurrentUser'); // ["email"=>"john@doe.com","hash"=>"4DFBT7W4567M23457N345678N345687"]
```



## License

ParameterTree is open-sourced software licensed under the MIT license