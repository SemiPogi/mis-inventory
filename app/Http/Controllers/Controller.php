<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Returns the department_id to filter queries by, or null if the
     * authenticated user can see all departments (admin / accounting).
     */
    protected function deptScope(): ?int
    {
        return auth()->user()?->departmentScope();
    }
}
