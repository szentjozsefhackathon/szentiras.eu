<?php

namespace SzentirasHu\Services\Tools;

class QuizGameAnimals
{
    /**
     * Get all available animals from the CSV file
     * 
     * @return array Array of animals with 'id', 'name', and 'svg_url' keys
     */
    public static function getAvailableAnimals(): array
    {
        $csvPath = public_path('animals/list.csv');
        
        if (!file_exists($csvPath)) {
            return [];
        }
        
        $animals = [];
        $handle = fopen($csvPath, 'r');
        
        if ($handle) {
            $firstLine = true;
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                
                // Remove UTF-8 BOM from first line if present
                if ($firstLine) {
                    $line = str_replace("\xEF\xBB\xBF", '', $line);
                    $firstLine = false;
                }
                
                if (empty($line)) {
                    continue;
                }
                
                $parts = explode(';', $line);
                if (count($parts) === 2) {
                    $id = trim($parts[0]);
                    $name = trim($parts[1]);
                    
                    $animals[] = [
                        'id' => $id,
                        'name' => $name,
                        'svg_url' => '/animals/' . $id . '.svg'
                    ];
                }
            }
            fclose($handle);
        }
        
        return $animals;
    }
    
    /**
     * Get available animals for a specific game (filtering out already taken ones)
     * 
     * @param array $game The game data
     * @return array Array of available animals
     */
    public static function getAvailableAnimalsForGame(array $game): array
    {
        $allAnimals = self::getAvailableAnimals();
        
        // Get list of taken animal IDs
        $takenAnimals = [];
        foreach ($game['players'] ?? [] as $player) {
            if (isset($player['animal_id'])) {
                $takenAnimals[] = $player['animal_id'];
            }
        }
        
        // Filter out taken animals
        return array_values(array_filter($allAnimals, function($animal) use ($takenAnimals) {
            return !in_array($animal['id'], $takenAnimals);
        }));
    }
    
    /**
     * Get animal data by ID
     * 
     * @param string $animalId The animal ID
     * @return array|null Array with 'id', 'name', 'svg_url' or null if not found
     */
    public static function getAnimalById(string $animalId): ?array
    {
        $allAnimals = self::getAvailableAnimals();
        
        foreach ($allAnimals as $animal) {
            if ($animal['id'] === $animalId) {
                return $animal;
            }
        }
        
        return null;
    }
}
