<?php

namespace App\Review\Services;

use App\Groq\InterfaceServices\GroqStructuredOutputInterface;
use Throwable;

/**
 * Labels a review positive, neutral or negative ("Auto-classified with sentiment analysis" on the
 * Movie page). Groq reads the text; when it can't be reached the member's stars decide instead.
 */
class SentimentClassifier
{
    /** @var list<string> */
    public const LABELS = ['positive', 'neutral', 'negative'];

    public function __construct(private readonly GroqStructuredOutputInterface $groq) {}

    public function classify(string $text, int $rating): string
    {
        try {
            $answer = $this->groq->askWithSchema(
                "Classify the sentiment of this movie review as positive, neutral or negative. The reviewer gave it {$rating} of 5 stars.\n\nReview:\n".mb_substr($text, 0, 2000),
                'review_sentiment',
                [
                    'type' => 'object',
                    'properties' => ['sentiment' => ['type' => 'string', 'enum' => self::LABELS]],
                    'required' => ['sentiment'],
                    'additionalProperties' => false,
                ],
                32,
            );

            if (in_array($answer['sentiment'] ?? null, self::LABELS, true)) {
                return $answer['sentiment'];
            }
        } catch (Throwable $e) {
            report($e);
        }

        return self::fromRating($rating);
    }

    public static function fromRating(int $rating): string
    {
        return $rating >= 4 ? 'positive' : ($rating === 3 ? 'neutral' : 'negative');
    }
}
