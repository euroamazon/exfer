<?php

namespace App\Services;

use App\Core\Database;

/**
 * Service de reconnaissance d'image (Vision IA)
 *
 * Mode stub : génère des données simulées pour les tests
 * Mode réel : appelle une API externe configurée
 */
class VisionService
{
    /** @var string 'stub' ou 'api' */
    private string $mode;

    /** @var string|null URL de l'API externe */
    private ?string $apiUrl;

    /** @var string|null Clé API */
    private ?string $apiKey;

    public function __construct()
    {
        $this->mode   = defined('VISION_MODE_BACKEND') ? VISION_MODE_BACKEND : 'stub';
        $this->apiUrl = defined('VISION_API_URL') ? VISION_API_URL : null;
        $this->apiKey = defined('VISION_API_KEY') ? VISION_API_KEY : null;
    }

    /**
     * Classifie une image et retourne les prédictions
     *
     * @param string $imagePath   Chemin absolu vers l'image
     * @param string $designation Désignation actuelle de l'article
     * @param float  $threshold   Seuil de confiance (0.0–1.0)
     * @return array ['predicted_label' => ..., 'confidence' => ..., 'top_k' => [...], 'mismatch' => bool]
     */
    public function classify(string $imagePath, string $designation = '', float $threshold = 0.75): array
    {
        if ($this->mode === 'stub') {
            return $this->stubClassify($imagePath, $designation, $threshold);
        }

        return $this->apiClassify($imagePath, $designation, $threshold);
    }

    /**
     * Vérifie si la prédiction est incohérente avec la désignation
     */
    public function checkMismatch(string $predictedLabel, string $designation, float $confidence, float $threshold): bool
    {
        if ($confidence < $threshold) {
            return false; // Confiance trop faible pour détecter un mismatch
        }

        // Comparaison simple : mots-clés communs
        $predWords = array_map('strtolower', preg_split('/[\s\-_,]+/', $predictedLabel));
        $descWords = array_map('strtolower', preg_split('/[\s\-_,]+/', $designation));

        // Supprimer les mots vides
        $stopWords  = ['le','la','les','un','une','des','de','du','et','ou','à','en','pour','par'];
        $predWords  = array_diff($predWords, $stopWords);
        $descWords  = array_diff($descWords, $stopWords);

        if (empty($predWords) || empty($descWords)) {
            return false;
        }

        // Vérifier intersection
        $common = array_intersect($predWords, $descWords);
        return count($common) === 0; // Mismatch si aucun mot en commun
    }

    /**
     * Crée une anomalie IMAGE_DESIGNATION_MISMATCH si pertinent
     */
    public function handleMismatch(
        int $itemId,
        int $campaignId,
        ?int $locationId,
        array $visionResult,
        string $designation,
        float $threshold,
        string $visionMode,
        AnomalyService $anomalyService
    ): ?int {
        if (!$this->checkMismatch($visionResult['predicted_label'], $designation, $visionResult['confidence'], $threshold)) {
            return null;
        }

        $severity = $visionMode === 'BLOCKING' ? 'BLOCKING' : 'WARNING';

        $anomalyId = $anomalyService->create(
            'IMAGE_DESIGNATION_MISMATCH',
            $severity,
            $campaignId,
            $locationId,
            $itemId,
            "L'image prédit '{$visionResult['predicted_label']}' (confiance: " . round($visionResult['confidence'] * 100) . "%) "
            . "mais la désignation est '{$designation}'",
            [
                'predicted_label' => $visionResult['predicted_label'],
                'confidence'      => $visionResult['confidence'],
                'designation'     => $designation,
                'top_k'           => $visionResult['top_k'] ?? [],
            ]
        );

        return $anomalyId;
    }

    // ─── Implémentations ──────────────────────────────────────────────────────

    /**
     * Mode stub : simule une classification
     */
    private function stubClassify(string $imagePath, string $designation, float $threshold): array
    {
        // Catégories simulées
        $categories = [
            'ordinateur', 'imprimante', 'bureau', 'chaise', 'climatiseur',
            'téléphone', 'projecteur', 'armoire', 'tableau blanc', 'serveur',
            'écran', 'scanner', 'copieur', 'véhicule', 'groupe électrogène',
        ];

        // Simuler une prédiction basée sur la désignation si possible
        $predicted   = null;
        $designation = strtolower($designation);

        foreach ($categories as $cat) {
            if (str_contains($designation, $cat)) {
                $predicted = $cat;
                break;
            }
        }

        if (!$predicted) {
            // Prédiction aléatoire (15% de chance de mismatch)
            $idx       = rand(0, count($categories) - 1);
            $predicted = $categories[$idx];
        }

        $confidence = round(0.60 + (rand(0, 35) / 100), 2);

        // Top-k simulé
        $shuffled = $categories;
        shuffle($shuffled);
        $topK = array_slice(array_map(function($cat) use ($confidence) {
            return ['label' => $cat, 'confidence' => round(rand(10, 90) / 100, 2)];
        }, array_slice($shuffled, 0, 5)), 0, 5);

        usort($topK, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

        return [
            'predicted_label' => $predicted,
            'confidence'      => $confidence,
            'top_k'           => $topK,
            'source'          => 'stub',
        ];
    }

    /**
     * Mode réel : appelle l'API de vision externe
     */
    private function apiClassify(string $imagePath, string $designation, float $threshold): array
    {
        if (!$this->apiUrl) {
            throw new \RuntimeException("L'URL de l'API Vision n'est pas configurée.");
        }

        if (!file_exists($imagePath)) {
            throw new \InvalidArgumentException("Le fichier image n'existe pas: {$imagePath}");
        }

        $imageData = base64_encode(file_get_contents($imagePath));
        $mimeType  = mime_content_type($imagePath) ?: 'image/jpeg';

        $payload = json_encode([
            'image'       => "data:{$mimeType};base64,{$imageData}",
            'designation' => $designation,
            'threshold'   => $threshold,
        ]);

        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
        ];

        if ($this->apiKey) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Erreur cURL: {$error}");
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("L'API Vision a retourné HTTP {$httpCode}");
        }

        $result = json_decode($response, true);
        if (!$result || !isset($result['predicted_label'])) {
            throw new \RuntimeException("Réponse API Vision invalide.");
        }

        $result['source'] = 'api';
        return $result;
    }
}
