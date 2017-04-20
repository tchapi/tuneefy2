<?php

/**
 * CustomViewHandler - view wrapper for json responses (with error code).
 *
 * @author tchapi <regbasket@gmail.com>, based on the work of Jonathan Tavares <the.entomb@gmail.com>
 * @license CC-BY-NC
 * @filesource
 */

namespace tuneefy\Utils;

use Slim\Slim;
use Slim\View;

class CustomViewHandler extends View
{
    /**
     * Bitmask consisting of <b>JSON_HEX_QUOT</b>,
     * <b>JSON_HEX_TAG</b>,
     * <b>JSON_HEX_AMP</b>,
     * <b>JSON_HEX_APOS</b>,
     * <b>JSON_NUMERIC_CHECK</b>,
     * <b>JSON_PRETTY_PRINT</b>,
     * <b>JSON_UNESCAPED_SLASHES</b>,
     * <b>JSON_FORCE_OBJECT</b>,
     * <b>JSON_UNESCAPED_UNICODE</b>.
     * The behaviour of these constants is described on
     * the JSON constants page.
     *
     * @var int
     */
    public $encodingOptions = 0;

    public function render(int $status = 200)//: void
    {
        $app = Slim::getInstance();
        $response = $this->all();

        // Remove error flag if no error
        if (!$this->has('error') || $this->get('error') === false) {
            unset($response['error']);
        }

        // Append status code to response
        $response['status'] = $status;
        $app->response()->status($status);

        // Add/Remove other pairs
        unset($response['flash']);

        // Handle custom return type, default to JSON
        $alt = $app->request->params('alt', 'json');
        if ($alt === 'xml') {
            $app->response()->header('Content-Type', 'application/xml;charset=UTF-8');
            // TODO
            $app->response()->body('<?xml version="1.0" encoding="UTF-8"?><msg></msg>');
        } elseif ($alt === 'json') {
            $app->response()->header('Content-Type', 'application/json;charset=UTF-8');
            $jsonp_callback = $app->request->get('callback', null);

            if ($jsonp_callback !== null) {
                $app->response()->body($jsonp_callback.'('.json_encode($response, $this->encodingOptions).')');
            } else {
                $app->response()->body(json_encode($response, $this->encodingOptions));
            }
        }

        $app->stop();
    }
}
