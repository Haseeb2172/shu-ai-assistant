<?php
/** Dependency-free checks for the domain logic; run with php tests/smoke.php. */
define( 'ABSPATH', __DIR__ );
define( 'AUTH_KEY', 'test-key-change-in-real-wordpress' );
define( 'AUTH_SALT', 'test-salt-change-in-real-wordpress' );

function shu_ai_get_options() {
	return array(
		'scoring_hot_keywords' => "today\nasap\nurgent",
		'scoring_cold_keywords' => "just researching\nnot urgent",
		'scoring_high_value_services' => 'Executive & Personal Protection',
	);
}
function wp_timezone() { return new DateTimeZone( 'America/Los_Angeles' ); }
function check( $actual, $expected, $label ) {
	if ( $actual !== $expected ) {
		throw new RuntimeException( $label . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}
}

require __DIR__ . '/../includes/class-shu-crypto.php';
require __DIR__ . '/../includes/class-shu-relevance-engine.php';
require __DIR__ . '/../includes/class-shu-lead-scoring.php';
require __DIR__ . '/../includes/class-shu-knowledge-base.php';

$cipher = SHU_Crypto::encrypt( "key-one\nkey-two" );
check( SHU_Crypto::decrypt( $cipher ), "key-one\nkey-two", 'encrypted key round trip' );
$raw = base64_decode( substr( $cipher, strlen( SHU_Crypto::ENCRYPTED_PREFIX ) ) );
$raw[30] = chr( ord( $raw[30] ) ^ 1 );
check( SHU_Crypto::decrypt( SHU_Crypto::ENCRYPTED_PREFIX . base64_encode( $raw ) ), '', 'modified key rejected' );

check( SHU_Lead_Scoring::score( array( 'service' => 'Residential Security', 'urgency' => 'not urgent' ) ), 'cold', 'negated urgency' );
check( SHU_Lead_Scoring::score( array( 'service' => 'Executive & Personal Protection', 'urgency' => 'just researching' ) ), 'hot', 'high value priority' );
check( SHU_Lead_Scoring::score( array( 'service' => 'Fire Watch', 'urgency' => 'today' ) ), 'hot', 'urgent priority' );
$soon = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '+3 days' )->format( 'Y-m-d' );
check( SHU_Lead_Scoring::score( array( 'service' => 'Event Security', 'urgency' => 'not sure yet', 'extra_fields' => array( 'event_date' => $soon ) ) ), 'hot', 'event date priority' );
check( SHU_Lead_Scoring::score( array( 'service' => 'Mobile Patrol', 'urgency' => 'next month' ) ), 'warm', 'default priority' );

$a = SHU_Relevance_Engine::compute_tf_vector( 'residential estate security' );
$b = SHU_Relevance_Engine::compute_tf_vector( 'estate security guard' );
check( SHU_Relevance_Engine::cosine_similarity( $a, $a ), 1.0, 'identical vectors' );
if ( SHU_Relevance_Engine::cosine_similarity( $a, $b ) <= 0 ) {
	throw new RuntimeException( 'Overlapping vector terms must rank above zero.' );
}
echo "Domain smoke checks passed.\n";
