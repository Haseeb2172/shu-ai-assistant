<?php
/**
 * In-PHP normalized term-frequency vectors and cosine similarity.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHU_Relevance_Engine {

	/**
	 * Compute a term frequency (TF) vector for a given text block.
	 *
	 * @param string $text
	 * @return array Key-value map of term => normalized frequency
	 */
	public static function compute_tf_vector( $text ) {
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
		$words = preg_split( '/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );

		$stopwords = array(
			'the', 'and', 'for', 'are', 'you', 'your', 'with', 'that', 'this',
			'have', 'what', 'how', 'can', 'about', 'does', 'from', 'when',
			'where', 'who', 'why', 'will', 'would', 'could', 'should', 'ours',
			'their', 'they', 'them', 'its', 'into', 'out', 'not', 'but',
		);

		$freq = array();
		$total = 0;

		foreach ( $words as $word ) {
			if ( strlen( $word ) < 3 || in_array( $word, $stopwords, true ) ) {
				continue;
			}
			if ( ! isset( $freq[ $word ] ) ) {
				$freq[ $word ] = 0;
			}
			$freq[ $word ]++;
			$total++;
		}

		if ( 0 === $total ) {
			return array();
		}

		$vector = array();
		foreach ( $freq as $term => $count ) {
			$vector[ $term ] = round( $count / $total, 6 );
		}

		return $vector;
	}

	/**
	 * Calculate cosine similarity between two term frequency vectors (0.0 to 1.0).
	 *
	 * @param array $vecA
	 * @param array $vecB
	 * @return float
	 */
	public static function cosine_similarity( array $vecA, array $vecB ) {
		if ( empty( $vecA ) || empty( $vecB ) ) {
			return 0.0;
		}

		$dot_product = 0.0;
		$normA = 0.0;
		$normB = 0.0;

		foreach ( $vecA as $term => $val ) {
			$normA += $val * $val;
			if ( isset( $vecB[ $term ] ) ) {
				$dot_product += $val * $vecB[ $term ];
			}
		}

		foreach ( $vecB as $val ) {
			$normB += $val * $val;
		}

		if ( 0.0 === $normA || 0.0 === $normB ) {
			return 0.0;
		}

		return round( $dot_product / ( sqrt( $normA ) * sqrt( $normB ) ), 6 );
	}

	/**
	 * Compute hybrid similarity score blending cosine similarity (70%)
	 * and keyword overlap score (30%).
	 *
	 * @param float $cosine_sim
	 * @param int $keyword_score
	 * @return float
	 */
	public static function hybrid_score( $cosine_sim, $keyword_score ) {
		// Normalize keyword score (assume keyword_score of 10+ is high)
		$normalized_kw = min( 1.0, $keyword_score / 10.0 );
		return round( ( 0.7 * $cosine_sim ) + ( 0.3 * $normalized_kw ), 6 );
	}
}
