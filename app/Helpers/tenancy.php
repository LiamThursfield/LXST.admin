<?php

if (! function_exists('is_tenant_scope')) {
    /**
     * Return true when the application is in a tenancy scope
     */
    function is_tenant_scope(): bool
    {
        return tenant() != null;
    }
}

if (! function_exists('is_central_scope')) {
    /**
     * Return true when the application is in the central scope
     */
    function is_central_scope(): bool
    {
        return tenant() == null;
    }
}
