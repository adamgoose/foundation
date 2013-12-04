<?php namespace Orchestra\Foundation\Routing;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;

abstract class BaseController extends Controller
{
    /**
     * Processor instance.
     *
     * @var object
     */
    protected $processor;

    /**
     * Base controller construct method.
     */
    public function __construct()
    {
        $this->setupFilters();
    }

    /**
     * Setup controller filters.
     *
     * @return void
     */
    abstract protected function setupFilters();

    /**
     * Show missing pages.
     *
     * GET (:orchestra) return 404
     *
     * @param  string   $method
     * @param  array    $parameters
     * @return Response
     */
    public function missingMethod($method, $parameters = array())
    {
        return Response::view('orchestra/foundation::dashboard.missing', $parameters, 404);
    }
}
