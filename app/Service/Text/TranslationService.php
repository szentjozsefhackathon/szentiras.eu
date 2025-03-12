<?php

namespace SzentirasHu\Service\Text;

use Illuminate\Support\Collection;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Repository\TranslationRepository;

class TranslationService {

    public function __construct(protected TranslationRepository $translationRepository) {
    }

    public function getDefaultTranslation() : Translation {
        $defaultTranslationAbbrev = \Config::get('settings.defaultTranslationAbbrev');
        return $this->translationRepository->getByAbbrev($defaultTranslationAbbrev);
    }

    public function getByAbbreviation($translationAbbrev) : Translation {
        return $this->translationRepository->getByAbbrev($translationAbbrev);
    }

    public function getById($translationId) : Translation {
        return $this->translationRepository->getById($translationId);
    }

    public function getAllTranslations() : Collection {
        return $this->translationRepository->getAll();
    }

}