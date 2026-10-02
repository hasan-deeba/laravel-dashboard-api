<?php

namespace App\Traits;


use App\Helpers\AppHelper;

trait ModelTrait
{
    public function transformList($items, $allowedKey = null): array
    {

        $result = array();
        foreach ($items as $item) {
            array_push($result, $item->transformItemInclude($allowedKey));
        }
        return $result;
    }

    public function transformItemInclude($allowedKey=[]): array
    {
        if (is_null($allowedKey) || count($allowedKey) == 0) {
            $allowedKey =$this->minimumAllowedKey;
        }
        $transformedArray = array();
        foreach ($allowedKey as $key) {
            $transformedArray [$key] = $this->getKeyValue($key);
        }
        return $transformedArray;
    }

    public function getKeyValue($key)
    {
        $lang = app()->getLocale();
        if (isset($this->multiLangFields) && in_array($key, $this->multiLangFields)) {
            $localizedKey = $key . '_' . $lang;
            return $this->$localizedKey ?? null;
        }

        if (isset($this->specialFields[$key])) {
            return $this->specialFields[$key]($this[$key]);
        }
        if (isset($this->extraFields[$key])) {
            return $this->extraFields[$key]($this[$key]);
        }
        if (isset($this[$key])) {
            return $this[$key];
        }
        return null;
    }

    public function transformItemExclude($notAllowedKey = []): array
    {
        $languages = AppHelper::languages();
        $langPattern = implode('|', array_map('preg_quote', $languages));

        $notAllowedKey = array_merge($notAllowedKey , $this->notAllowedKey);
        $transformedArray = array();

        foreach ($this->getAttributes() as $key => $value) {
            if (preg_match("/_({$langPattern})$/", $key)) {
                continue;
            }
            if (!in_array($key, $notAllowedKey)) {
                $transformedArray [$key] = $this->getKeyValue($key);
            }
        }

        foreach ($this->multiLangFields ?? [] as $field) {
            if (!in_array($field, $notAllowedKey)) {
                $transformedArray[$field] = $this->getKeyValue($field);
            }
        }


        foreach ($this->extraFields as $key => $value) {
            if (!in_array($key, $notAllowedKey)) {
                $transformedArray[$key] = $this->extraFields[$key]();
            }
        }
        return $transformedArray;
    }
}
