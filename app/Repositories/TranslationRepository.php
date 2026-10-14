<?php

namespace App\Repositories;

use App\Contracts\Repositories\TranslationRepositoryInterface;
use App\Models\Translation;

class TranslationRepository implements TranslationRepositoryInterface
{
    public function __construct(
       private readonly Translation $translation
    )
    {
    }

    public function add(object $request, string $model, int|string $id): bool
    {
        foreach ($request->lang as $index => $key) {
            foreach (['name','description','title'] as $type){
                $value = $this->getValueOrAutoTranslate(request: $request, type: $type, index: $index, locale: $key);
                if (isset($value) && $key != 'en') {
                    $this->translation->insert(
                        [
                            'translationable_type' => $model,
                            'translationable_id' => $id,
                            'locale' => $key,
                            'key' => $type,
                            'value' => $value
                        ]
                    );
                }
            }
        }
        return true;
    }

    public function update(object $request, string $model, int|string $id): bool
    {
        foreach ($request->lang as $index => $key) {
            foreach (['name','description','title'] as $type){
                $value = $this->getValueOrAutoTranslate(request: $request, type: $type, index: $index, locale: $key);
                if (isset($value) && $key != 'en') {
                    $this->translation->updateOrInsert(
                        [
                            'translationable_type' => $model,
                            'translationable_id' => $id,
                            'locale' => $key,
                            'key' => $type
                        ],
                        [
                            'value' => $value
                        ]
                    );
                }
            }
        }
        return true;
    }

    /**
     * The value typed for this language or, when it was left blank, a machine
     * translation of the English value so the page doesn't fall back to English.
     */
    private function getValueOrAutoTranslate(object $request, string $type, int|string $index, string $locale): mixed
    {
        $value = $request[$type][$index] ?? null;
        $isBlank = !isset($value) || (is_string($value) && trim(strip_tags($value)) === '');
        if (!$isBlank || $locale == 'en' || !is_array($request[$type] ?? null)) {
            return $value;
        }

        $englishIndex = array_search('en', (array)$request->lang);
        $english = $englishIndex === false ? null : ($request[$type][$englishIndex] ?? null);
        return is_string($english) ? autoTranslateContent(content: $english, locale: $locale) : null;
    }
    public function updateData(string $model, string $id, string $lang, string $key, string $value):bool
    {
        $this->translation->updateOrInsert(
            [
                'translationable_type' => $model,
                'translationable_id' => $id,
                'locale' => $lang,
                'key' => $key
            ],
            [
                'value' => $value
            ]
        );
        return true;
    }
    public function delete(string $model, int|string $id): bool
    {
        $this->translation->where('translationable_type',$model)->where('translationable_id',$id)->delete();
        return true;
    }
}
