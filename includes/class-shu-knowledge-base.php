<?php
/**
 * Knowledge base: auto-indexes published Pages/Posts into ~500-800 word
 * chunks stored in wp_shu_chat_kb with term-frequency vectors, re-indexes on save_post,
 * and provides hybrid cosine similarity + keyword relevance search.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHU_Knowledge_Base {

	/** Target word count per chunk (soft bounds 500-800 per spec). */
	const CHUNK_MIN_WORDS = 500;
	const CHUNK_MAX_WORDS = 800;

	public function __construct() {
		add_action( 'save_post', array( $this, 'reindex_single_post' ), 10, 3 );
	}

	/**
	 * Re-index a single post when it's saved, if it's a published page/post.
	 */
	public function reindex_single_post( $post_id, $post, $update ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}

		global $wpdb;
		$kb_table = $wpdb->prefix . 'shu_chat_kb';

		$wpdb->delete( $kb_table, array( 'post_id' => $post_id ), array( '%d' ) );

		if ( 'publish' === $post->post_status ) {
			$this->index_post( $post );
		}
	}

	/**
	 * Full re-crawl of all published pages/posts.
	 *
	 * @return int Number of chunks created.
	 */
	public function rebuild_full_index() {
		global $wpdb;
		$kb_table = $wpdb->prefix . 'shu_chat_kb';

		$wpdb->query( "TRUNCATE TABLE {$kb_table}" );

		$total_chunks = 0;
		$page = 1;
		do {
			$query = new WP_Query( array(
				'post_type' => array( 'post', 'page' ), 'post_status' => 'publish',
				'posts_per_page' => 100, 'paged' => $page++, 'no_found_rows' => true,
				'orderby' => 'ID', 'order' => 'ASC',
			) );
			foreach ( $query->posts as $post ) {
				$total_chunks += $this->index_post( $post );
			}
		} while ( count( $query->posts ) === 100 );
		wp_reset_postdata();

		update_option( 'shu_ai_kb_last_indexed', current_time( 'mysql' ) );
		update_option( 'shu_ai_kb_chunk_count', $total_chunks );

		return $total_chunks;
	}

	/**
	 * Strip post to plain text, chunk it, compute term-frequency vector, and insert rows.
	 *
	 * @param WP_Post $post
	 * @return int Number of chunks inserted for this post.
	 */
	private function index_post( $post ) {
		global $wpdb;
		$kb_table = $wpdb->prefix . 'shu_chat_kb';

		$plain_text = $this->post_to_plain_text( $post );

		if ( empty( trim( $plain_text ) ) ) {
			return 0;
		}

		$chunks = $this->chunk_text( $plain_text );
		$url    = get_permalink( $post );
		$now    = current_time( 'mysql' );
		$count  = 0;

		foreach ( $chunks as $chunk ) {
			$vector = SHU_Relevance_Engine::compute_tf_vector( $chunk );

			$wpdb->insert(
				$kb_table,
				array(
					'source_url'    => $url,
					'post_id'       => $post->ID,
					'title'         => $post->post_title,
					'content_chunk' => $chunk,
					'vector'        => wp_json_encode( $vector ),
					'updated_at'    => $now,
				),
				array( '%s', '%d', '%s', '%s', '%s', '%s' )
			);
			$count++;
		}

		return $count;
	}

	/**
	 * Convert post content to plain text.
	 */
	private function post_to_plain_text( $post ) {
		$content = $post->post_content;
		$content = strip_shortcodes( $content );
		$content = wp_strip_all_tags( $content );
		$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );
		$content = preg_replace( '/\s+/u', ' ', $content );

		$title = html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' );

		return trim( $title . '. ' . $content );
	}

	/**
	 * Split text into ~500-800 word chunks.
	 */
	private function chunk_text( $text ) {
		$sentences = preg_split( '/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );

		$chunks     = array();
		$current    = '';
		$current_wc = 0;

		foreach ( $sentences as $sentence ) {
			$sentence_wc = str_word_count( $sentence );

			if ( $current_wc + $sentence_wc > self::CHUNK_MAX_WORDS && $current_wc >= self::CHUNK_MIN_WORDS ) {
				$chunks[]   = trim( $current );
				$current    = '';
				$current_wc = 0;
			}

			$current   .= ( '' === $current ? '' : ' ' ) . $sentence;
			$current_wc += $sentence_wc;
		}

		if ( '' !== trim( $current ) ) {
			$chunks[] = trim( $current );
		}

		return $chunks;
	}

	private $synonym_map = array(
		'bodyguard'    => array( 'executive', 'protection' ),
		'bodyguards'   => array( 'executive', 'protection' ),
		'guard'        => array( 'security', 'officer' ),
		'guards'       => array( 'security', 'officer' ),
		'cop'          => array( 'security', 'officer' ),
		'cops'         => array( 'security', 'officer' ),
		'patrol'       => array( 'mobile', 'patrol' ),
		'patrols'      => array( 'mobile', 'patrol' ),
		'watchman'     => array( 'fire', 'watch' ),
		'firewatch'    => array( 'fire', 'watch' ),
		'wedding'      => array( 'event', 'security' ),
		'party'        => array( 'event', 'security' ),
		'concert'      => array( 'event', 'security' ),
		'home'         => array( 'residential', 'security' ),
		'house'        => array( 'residential', 'security' ),
		'neighborhood' => array( 'residential', 'patrol' ),
		'office'       => array( 'commercial', 'warehouse' ),
		'warehouse'    => array( 'commercial', 'warehouse' ),
		'store'        => array( 'commercial', 'warehouse' ),
		'business'     => array( 'commercial', 'warehouse' ),
		'exec'         => array( 'executive', 'protection' ),
		'celebrity'    => array( 'executive', 'protection' ),
		'vip'          => array( 'executive', 'protection' ),
		'license'      => array( 'bsis', 'licensed', 'ppo' ),
		'licensed'     => array( 'bsis', 'ppo' ),
		'cost'         => array( 'pricing', 'price' ),
		'pricing'      => array( 'cost', 'price' ),
		'coverage'     => array( 'area', 'location', 'serve' ),
		'areas'        => array( 'coverage', 'location' ),
	);

	/**
	 * Hybrid vector cosine similarity + keyword-overlap relevance search.
	 *
	 * @param string $query
	 * @param int    $limit
	 * @return array[] Each item: ['title' => string, 'source_url' => string, 'content_chunk' => string, '_score' => float]
	 */
	public function search( $query, $limit = 5 ) {
		global $wpdb;
		$kb_table = $wpdb->prefix . 'shu_chat_kb';

		$keywords     = self::extract_keywords( $query );
		$expanded_kws = $this->expand_with_synonyms( $keywords );

		if ( empty( $keywords ) ) {
			return array();
		}

		$query_vector = SHU_Relevance_Engine::compute_tf_vector( implode( ' ', $expanded_kws ) );

		$like_clauses = array();
		$params       = array();

		foreach ( $expanded_kws as $word ) {
			$like_clauses[] = 'content_chunk LIKE %s OR title LIKE %s';
			$like_term      = '%' . $wpdb->esc_like( $word ) . '%';
			$params[]       = $like_term;
			$params[]       = $like_term;
		}

		// Candidate recall must not depend entirely on literal LIKE matches. Rank
		// recent chunks as well, so related wording can receive a cosine score.
		$sql  = "SELECT id, source_url, title, content_chunk, vector FROM {$kb_table} WHERE " . implode( ' OR ', $like_clauses ) . ' ORDER BY id DESC LIMIT 200';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		$recent = $wpdb->get_results( "SELECT id, source_url, title, content_chunk, vector FROM {$kb_table} ORDER BY id DESC LIMIT 300", ARRAY_A );
		$by_id = array();
		foreach ( array_merge( (array) $rows, (array) $recent ) as $row ) {
			$by_id[ $row['id'] ] = $row;
		}
		$rows = array_values( $by_id );

		if ( empty( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$kw_score   = $this->score_chunk( $row, $expanded_kws );
			$doc_vector = ! empty( $row['vector'] ) ? json_decode( $row['vector'], true ) : SHU_Relevance_Engine::compute_tf_vector( $row['content_chunk'] );
			$doc_vector = is_array( $doc_vector ) ? $doc_vector : array();

			$cosine_sim    = SHU_Relevance_Engine::cosine_similarity( $query_vector, $doc_vector );
			$row['_score'] = SHU_Relevance_Engine::hybrid_score( $cosine_sim, $kw_score );
		}
		unset( $row );

		usort(
			$rows,
			function ( $a, $b ) {
				return $b['_score'] <=> $a['_score'];
			}
		);

		$top = array_values( array_filter( array_slice( $rows, 0, $limit ), function ( $row ) {
			return $row['_score'] >= 0.08;
		} ) );

		return array_map(
			function ( $row ) {
				return array(
					'title'         => $row['title'],
					'source_url'    => $row['source_url'],
					'content_chunk' => $row['content_chunk'],
					'_score'        => $row['_score'],
				);
			},
			$top
		);
	}

	/**
	 * Score chunk by keyword overlap count.
	 */
	private function score_chunk( $row, $keywords ) {
		$score       = 0;
		$body_lower  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $row['content_chunk'] ) : strtolower( $row['content_chunk'] );
		$title_lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $row['title'] ) : strtolower( $row['title'] );

		foreach ( $keywords as $word ) {
			$body_count = substr_count( $body_lower, $word );
			$score     += $body_count;

			if ( false !== strpos( $title_lower, $word ) ) {
				$score += 3;
			}
		}

		return $score;
	}

	private function expand_with_synonyms( array $keywords ) {
		$expanded = $keywords;

		foreach ( $keywords as $word ) {
			if ( isset( $this->synonym_map[ $word ] ) ) {
				$expanded = array_merge( $expanded, $this->synonym_map[ $word ] );
			}
		}

		return array_unique( $expanded );
	}

	/** Shared tokenizer for retrieval and the analytics question themes. */
	public static function extract_keywords( $query ) {
		$stopwords = array(
			'the', 'and', 'for', 'are', 'you', 'your', 'with', 'that', 'this',
			'have', 'what', 'how', 'can', 'about', 'does', 'from', 'when',
			'where', 'who', 'why', 'will', 'would', 'could', 'should', 'ours',
			'their', 'they', 'them', 'its', 'into', 'out', 'not', 'but',
		);

		$query = function_exists( 'mb_strtolower' ) ? mb_strtolower( $query ) : strtolower( $query );
		$words = preg_split( '/[^\p{L}\p{N}]+/u', $query, -1, PREG_SPLIT_NO_EMPTY );

		$keywords = array();
		foreach ( $words as $word ) {
			if ( strlen( $word ) < 3 || in_array( $word, $stopwords, true ) ) {
				continue;
			}
			$keywords[] = $word;
		}

		return array_unique( $keywords );
	}

	public function format_chunks_for_prompt( array $chunks ) {
		if ( empty( $chunks ) ) {
			return '(No closely matching site content was found for this question — rely on the general business context and key facts above, and avoid inventing specifics you are not given.)';
		}

		$out = array();
		foreach ( $chunks as $chunk ) {
			$out[] = "Source: {$chunk['title']} ({$chunk['source_url']})\n{$chunk['content_chunk']}";
		}

		return implode( "\n\n---\n\n", $out );
	}
}
