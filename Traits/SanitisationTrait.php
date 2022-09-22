<?php

/**
 * Created by William Armillei
 * Date: 26/08/2021
 */

namespace App\Traits;

trait SanitisationTrait
{
    public function sanitizeInputArray($inputArray)
    {
        $output = [];

        foreach ($inputArray AS $key => $input){
            if (is_array($input)){
                $output[$key] = $this->sanitisationInputArray($input);
                continue;
            }
            $output[$key] = e($input);
        }
        return $output;
    }
}
