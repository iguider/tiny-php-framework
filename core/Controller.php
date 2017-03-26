<?php

abstract class Controller
{
    public $jwt;

    public function execute(Request $request)
    {
        $response = Response::factory();
        if ($request->is_protected()) {
            $authHeader = $request->headers('authorization');
            if ($authHeader) {
                list($jwt) = sscanf($authHeader, 'Bearer %s');
                if ($jwt) {
                    try {
                        $this->jwt = JWT::decode($jwt, Config::JWT_KEY);
                    } catch (Exception $e) {
                        return Response::HTTPError(401, $e->getMessage());
                    }
                } else {
                    return Response::HTTPError(401, "Invalid token");
                }
            } else {
                return Response::HTTPError(401, "Token not found in the request");
            }
        }
        $action = 'action_'.$request->action();
        if (!method_exists($this, $action)) {
            return Response::HTTPError(404, "Unable to find an action to match the URI: {$request->uri()}");
        }
        $this->{$action}($request, $response);

        return $response;
    }
}
