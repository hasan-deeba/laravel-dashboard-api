<?php


namespace App\Services;




class ResultService
{
    public bool $valid;
    public int $code;
    public string $message;
    public ?array $item;

    function __construct($valid = true, $code = 200, $message = 'success', $item = null){
        $this->message = $message;
        $this->valid = $valid;
        $this->code = $code;
        $this->item = $item;
    }

}
