<?php

namespace Romega\Clocktower;

use Illuminate\Http\Request;

trait HasAPIMethods
{
	/*
	 * The model this controller utilizes
	*/
	protected $model;
	
	/*
	 * Define the Fractal transformer to use
	 * prior to returning elements in assumed
	 * methods
	*/
	protected $transformer;
	
	/*
	 * This controller assumes some methods
	 * defining this array will allow users to 
	 * limit the available methods on the controller
	 * @see $this->getProtectedMethods()
	*/
	protected $allowedMethods = [];
	
	/*
	 * An internal variable to store and access 
	 * Request data
	*/
	protected $attributes;

	/*
	 * An array that defines what includes
	 * to return on the index method
	*/
	protected $includes = [];

	/*
	 * An array that defines what includes
	 * to return on the index method
	*/
	protected $indexIncludes = [];

	/*
	 * An array that defines what includes
	 * to return on the show method
	*/
	protected $showIncludes = [];
	
	/*
	 * Array of methods to protect by policy
	*/
	protected $policy;

	/*
	* Array of validation rules
	*/
	protected $storeValidationRules = [];

	/*
	* Array of custom messages for validation rules
	*/
	protected $storeValidationMessages =[];

	/*
	* Array of validation rules
	*/
	protected $updateValidationRules = [];

	/*
	* Array of custom messages for validation rules
	*/
	protected $updateValidationMessages =[];

	/*
	* Array of validation rules for store and update
	*/
	protected $validationRules = [];
	
	/*
	* Array of custom messages for validation rules for store and update
	*/
	protected $validationMessages = [];

	public function __construct(Request $request)
	{
	    $this->attributes = $request;

		if($this->attributes->include){
			$this->includes = $this->attributes->include;
		}
	}

    /**
     * Execute an action on the controller.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function callAction($method, $parameters)
    {
    	if(
    		collect($this->getAllowedMethods())->contains($method)
    		&& !collect($this->getProtectedMethods())->contains($method)
    	){
	        throw new \UnauthorizedMethodCallException("Method [{$method}] is not authorized to run on [".get_class($this).'].');
	    }

        return call_user_func_array([$this, $method], $parameters);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
    	if($this->getPolicy('index')){
	        $this->authorize('index',$this->getModel());
	    }

        return $this->returnIndex(
        	$this->getIndexCollectionScope(),
        	$this->getIndexTransformer(),
        	$this->getIndexIncludes()
        );
    }

    public function returnIndex($collection,$transformer,$includes = [])
    {
    	return fractal()
    	       ->collection( $collection )
    	       ->transformWith( $transformer )
    	       ->parseIncludes( $includes )
    	       ->toArray();
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
    	if($this->getPolicy('view')){
	        $this->authorize('view', $this->getShowItemScope($request));
	    }

        return fractal()
               ->item( $this->getShowItemScope($request) )
               ->transformWith( $this->getTransformer() )
               ->parseIncludes( $this->getShowIncludes() )
               ->toArray();
    }

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request)
	{
    	if($this->getPolicy('create')){
	        $this->authorize('create', $this->getModel());
	    }

	    $request->validate(
	    	array_merge($this->validationRules,$this->storeValidationRules),
	    	array_merge($this->validationMessages,$this->storeValidationMessages)
	    );

		$model = $this->getModel();
		$model = new $model;

		$created_model = $model->fill($request->input());

	    $this->getStoreAfterFillHook($created_model);

	    $created_model->save();

	    $this->getStoreAfterSaveHook($created_model, $request);

	    $return = fractal()
	               ->item($created_model->fresh())
	               ->transformWith( $this->getStoreTransformer() )
	               ->toArray();
	    

	    return response()->json(array_merge(['created'=>true],$return));
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request)
	{
	    $request->validate(
	    	array_merge($this->validationRules,$this->updateValidationRules),
	    	array_merge($this->validationMessages,$this->updateValidationMessages)
	    );

    	$model = $this->getModel();
    	$model = new $model;
    	$find_model = $model->findOrFail($request->id);

		if($this->getPolicy('update')){
	        $this->authorize('update', $find_model);
	    }

    	$find_model->fill($request->toArray());

	    $this->getUpdateAfterFillHook($find_model);

	    $find_model->save();

	    $this->getUpdateAfterSaveHook($find_model, $request);

	    return fractal()
	           ->item( $this->getShowItemScope($request) )
	           ->transformWith( $this->getTransformer() )
	           ->parseIncludes( $this->getShowIncludes() )
	           ->toArray();
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function destroy(Request $request)
	{
		$model = $this->getModel();
		$model = new $model;
		$find_model = $model->findOrFail($request->id);

    	if($this->getPolicy('delete')){
	        $this->authorize('delete', $find_model);
	    }

	    $this->getModel()::destroy($find_model->id);

	    return response()->json(['deleted'=>true]);
	}

	/**
	 * Get the model associated with the controller.
	 *
	 * @return string
	 */
	public function getModel()
	{
	    return $this->model;
	}

	/**
	 * Set the model associated with the controller.
	 *
	 * @param  string  $table
	 * @return $this
	 */
	public function setModel($model)
	{
	    $this->model = $model;

	    return $this;
	}

	/**
	 * Get the transformer associated with the controller.
	 *
	 * @return string
	 */
	public function getTransformer()
	{
	    return $this->transformer;
	}

	/**
	 * Set the transformer associated with the controller.
	 *
	 * @param  string  $table
	 * @return $this
	 */
	public function setTransformer($transformer)
	{
	    $this->transformer = $transformer;

	    return $this;
	}

	/**
	 * Get the allowed methods.
	 *
	 * @return string
	 */
	public function getAllowedMethods()
	{
		if (count($this->allowedMethods) === 0) {
		    return $this->getProtectedMethods();
		}

	    return $this->allowedMethods;
	}

	/**
	 * Set the allowed methods on this controller.
	 *
	 * @param  string  $table
	 * @return $this
	 */
	public function setAllowedMethods($allowedMethods)
	{
	    $this->allowedMethods = $allowedMethods;

	    return $this;
	}

	/**
	 * Get includes to parse on all methods.
	 *
	 * @return string
	 */
	public function getIncludes()
	{
		if (! isset($this->includes)) {
		    return $this->includes;
		}

	    return [];
	}

	/**
	 * Get includes to parse on the index method.
	 *
	 * @return string
	 */
	public function getIndexIncludes()
	{
	    return array_merge($this->indexIncludes,$this->getIncludes());
	}

	/**
	 * Set includes to parse on the index method.
	 *
	 * @param  string  $table
	 * @return $this
	 */
	public function setIndexIncludes($includes)
	{
	    $this->indexIncludes = $includes;

	    return $this;
	}

	/**
	 * Get includes to parse on the show method.
	 *
	 * @return string
	 */
	public function getShowIncludes()
	{
	    return array_merge($this->showIncludes,$this->getIncludes());
	}

	/**
	 * Set includes to parse on the show method.
	 *
	 * @param  string  $table
	 * @return $this
	 */
	public function setShowIncludes($includes)
	{
	    $this->showIncludes = $includes;

	    return $this;
	}

	/**
	 * Get the methods we want to protect.
	 *
	 * @return string
	 */
	public function getProtectedMethods()
	{
	    return ['index','show','store','destroy','update'];
	}

	/**
	 * Get the methods we want to protect.
	 *
	 * @param string $key
	 * @return string
	 */
	public function getPolicy($key)
	{
		if (collect($this->policy)->contains($key)) {
		    return $this->policy;
		}

	    return false;
	}

	/**
	 * Alter how the index collections are scoped.
	 *
	 * @return \Illuminate\Database\Eloquent\Collection|static[]
	 */
	public function getIndexCollectionScope(){
        $class = static::class;

        if (method_exists($class, 'setIndexCollectionScope')) {
            return $this->setIndexCollectionScope();
        }

		return $this->getModel()::get();
	}

	/**
	 * Alter how the index collections are transformed
	 *
	 * @return \App\Transformers\Class
	 */
	public function getIndexTransformer(){
        $class = static::class;

        if (method_exists($class, 'setIndexTransformer')) {
            return $this->setIndexTransformer();
        }

		return $this->getTransformer();
	}

	/**
	 * Alter how the show item is scoped.
	 *
	 * @return \Illuminate\Database\Eloquent\Collection|static[]
	 */
	public function getShowItemScope($request){
        $class = static::class;

        if (method_exists($class, 'setShowItemScope')) {
            return $this->setShowItemScope($request);
        }

		return $this->getModel()::find($request->id);
	}

	/**
	 * Alter how the store collections are transformed
	 *
	 * @return \App\Transformers\Class
	 */
	public function getStoreTransformer(){
        $class = static::class;

        if (method_exists($class, 'setStoreTransformer')) {
            return $this->setStoreTransformer();
        }

		return $this->getTransformer();
	}

	/**
	 * Perform additional steps after fill on Store method
	 *
	 * @return void
	 */
	public function getStoreAfterFillHook($model){
		$class = static::class;

		if (method_exists($class, 'setStoreAfterFillHook')) {
		    return $this->setStoreAfterFillHook($model);
		}
	}

	/**
	 * Perform additional steps after save on Store method
	 *
	 * @return void
	 */
	public function getStoreAfterSaveHook($model, $request){
		$class = static::class;

		if (method_exists($class, 'setStoreAfterSaveHook')) {
		    return $this->setStoreAfterSaveHook($model, $request);
		}
	}

	/**
	 * Perform additional steps after fill on Update method
	 *
	 * @return void
	 */
	public function getUpdateAfterFillHook($model){
		$class = static::class;

		if (method_exists($class, 'setUpdateAfterFillHook')) {
		    return $this->setUpdateAfterFillHook($model);
		}
	}

	/**
	 * Perform additional steps after save on Update method
	 *
	 * @return void
	 */
	public function getUpdateAfterSaveHook($model, $request){
		$class = static::class;

		if (method_exists($class, 'setUpdateAfterSaveHook')) {
		    return $this->setUpdateAfterSaveHook($model, $request);
		}
	}

}