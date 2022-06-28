# di-container
A simple yet powerful container with auto wiring


## Installation

```
composer require corviz/di-container
```

## Usage

Here are some common scenarios you will meet when using container + dependency injection

For these examples, we will assume the following classes:

```php
class A
{
    public funtion doSomething(){ /* ... */ }
    public function __construct() { echo "new instance of A"; }
}

class B
{
    public function __construct(A $a) { echo "new instance of B"; }
}
```

### Retrieve a new instance from container

Note that no previous declaration was required!

```php
use Corviz\DI\Container;

$container = new Container();
$a = $container->get(A::class);

$a->doSomething(); //It works! :)
```

### Auto-wiring and parameter types

What if we need a new instance of B?

```php
$b = $container->get(B::class);

//Outputs:
//new instance of A
//new instance of B
```

Because we declared a parameter with type "A" in the constructor, and it is a class, our container will try to 
create a new instance automatically. This is done though Reflection.

If we needed to receive a primitive type as parameter, we have to declare a default value otherwise the container
will throw a ContainerException, since it can't guess how to fill it.

### Manual setup

You may want to set some objects manually in some particular cases. To do so, simply use 'set' method.

```php
//Whenever this interface is met, the container will declare a class instance instead:
$container->set(AInterface::class, A::class);

//Declaring a constructor. This function will be called whenever 'B' class is met.
//Note: a closure is REQUIRED, in this case
$container->set(B::class, function (){
    $param1 = new A();
    
    return new B($param);
});

//Defining an instance. Whenever this interface is met, the same object will be accessed
$container->set(AInterface::class, new A());
```

### Aliases

If we want to alias something, all we have to do is set it in our container as usual

```php
$container->set('s3', new S3Client(/* ... */));

//...

$s3 = $container->get('s3');
```

### Singletons

When we want to access the same object through the entire execution, all we have to do is use 'setSingleton'.
It accepts the same definition variants as 'set'. In other words, you may use the class name, a constructor closure
or an object instance.

```php
$container->setSingleton(Queue::class, function(){
    $queue = new Queue();
    
    //setup...
    
    return $queue;
});

$queue = $container->get(Queue::class);
$queue->add(/* ... */);
echo $queue->count(); //1

//Somewhere else:

$queue = $container->get(Queue::class);
$queue->add(/* ... */);
echo $queue->count(); //2
```
