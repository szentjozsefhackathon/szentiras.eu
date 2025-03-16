<?php
/**

 */

namespace SzentirasHu\Data\Repository;


use SzentirasHu\Data\Entity\Synonym;

class SynonymRepositoryEloquent implements SynonymRepository
{

    function findSynonyms($word)
    {
        $synonym = Synonym::where('word', $word)->first();
        if ($synonym) {
            return Synonym::where('group', $synonym->group)->get();
        } else {
            return false;
        }
    }

    function addSynonyms($synonyms)
    {
        $existing = Synonym::whereIn('word', $synonyms)->first();
        if (!empty($existing)) {
            $group = $existing->group;
            $existingWord = $existing->word;
            foreach ($synonyms as $newWord) {
                if ($newWord != $existingWord) {
                    $newSynonym = new Synonym();
                    $newSynonym->word = $newWord;
                    $newSynonym->group = $group;
                    $newSynonym->save();
                }
            }
        } else {
            $firstSynonym = new Synonym();
            $firstSynonym->save(); // save to get the group ID
            $firstWord = array_pop($synonyms);
            $firstSynonym->word = $firstWord;
            $firstSynonym->group = $firstSynonym->id;
            $firstSynonym->save();
            foreach ($synonyms as $otherWord) {
                $otherSynonym = new Synonym();
                $otherSynonym->word = $otherWord;
                $otherSynonym->group = $firstSynonym->group;
                $otherSynonym->save();
            }
        }
    }

}
