<?php
/* Battle Plan Web Design - Jobsite GEO AI Prompts
   All Claude API prompts in one place for easy editing.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Job Description Rewrite
# Service Page Intro
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Job Description Rewrite
--------------------------------------------------------------*/

function bp_geo_prompt_rewrite_description( string $company, string $company_name_instruction, string $location_note, string $existing_terms_str, string $raw_description ): string {
	return <<<PROMPT
You are an SEO copywriter for a home services company called "{$company}".

A technician has written a rough job description after completing a service call. Your job is to:

1. Rewrite the description into polished, professional, SEO-friendly content.
   - Fix grammar, spelling, and punctuation.
   - Write in a helpful, trustworthy tone — not salesy.
   - Use natural length appropriate to the content (don't pad thin notes, don't truncate rich ones).
   - Naturally incorporate the city and state into the writing where it makes sense for local SEO.
   - NEVER include the street address or zip code in the output.
   - Target an 8th grade reading level. Keep sentences short and clear. Avoid run-on sentences.
     A compound sentence here and there is fine, but prefer simple, direct sentences overall.
   - COMPANY NAME INSTRUCTION: {$company_name_instruction}
       * Never repeat the company name more than once in a single post.
       * Never use formal legal names like "LLC", "Inc", or "Corp" — use only the clean business name.

2. Classify the job into exactly one service category from the list below.
   - Return ONLY the base service slug (no city/state appended) — e.g. "air-conditioner-repair", "heating-installation".
   - Use full words in slugs — never abbreviations. "ac" should always be "air-conditioner". "a/c" should be "air-conditioner".
   - Choose the best match based on what the job actually was, not just keywords. Use context and nuance.
   - If no existing term fits well, invent a clean, lowercase, hyphenated slug using full words.

{$location_note}

EXISTING SERVICE TERMS (slugs):
{$existing_terms_str}

RAW TECHNICIAN DESCRIPTION:
{$raw_description}

Respond ONLY with a valid JSON object. No preamble, no markdown, no explanation. Format:
{
  "rewritten_description": "...",
  "service_category": "..."
}
PROMPT;
}


/*--------------------------------------------------------------
# Service Page Intro
--------------------------------------------------------------*/

function bp_geo_prompt_service_intro( string $company, string $service, string $city, string $state, string $wiki ): string {

	$opening_para = rand( 1, 7 );
	$second_para = rand( 1, 5 );
	$choose_h2 = rand( 1, 5 );
	$local_para  = rand( 1, 7 );
	$wrap_up_para  = rand( 1, 5 );
	$map_caption  = rand( 1, 5 );

	$prompt = "You are an SEO copywriter for an HVAC home services company called \"{$company}\".

Write a service page intro for:
- Company: {$company}
- Service: {$service}
- City, State: {$city}, {$state}

OPENING PARAGRAPH - ";
	if ( $opening_para === 1 ) {
		$prompt .= "Start with something that goes wrong for a homeowner before they call; a symptom, a breakdown moment, a frustration.";
	} elseif ( $opening_para === 2 ) {
		$prompt .= "Start with a weather or seasonal observation told from the homeowner's daily experience, not a weather report.";
	} elseif ( $opening_para === 3 ) {
		$prompt .= "Start with what makes {$service} worth doing right; what's at stake if it's skipped or done poorly.";
	} elseif ( $opening_para === 4 ) {
		$prompt .= "Start with a direct, confident statement about {$company} and what they do for {$city} homeowners.";
	} elseif ( $opening_para === 5 ) {
		$prompt .= "Start with the financial angle; what ignoring this costs over time, in bills or repairs.";
	} elseif ( $opening_para === 6 ) {
		$prompt .= "Start with a counterintuitive or surprising fact about {$service} that most homeowners don't know.";
	} else {
		$prompt .= "Start with a question the homeowner is asking themselves right now about their system.";
	}
	$prompt .= "Introduce {$company} and {$service} naturally. Use <strong> tags around the company name on first use only. First-person plural (\"we\", \"our\") for the rest of the paragraph.

SECOND PARAGRAPH - ";
	if ( $second_para === 1 ) {
		$prompt .= "Speak to the homeowner directly. What does getting {$service} right protect them from? What happens if it's ignored?";
	} elseif ( $second_para === 2 ) {
		$prompt .= "Build trust by showing empathy for the homeowner's situation and confidence in the solution. Use a reassuring, conversational tone.";
	} elseif ( $second_para === 3 ) {
		$prompt .= "Address a common misconception homeowners have about {$service} — what they assume vs. what's actually true.";
	} elseif ( $second_para === 4 ) {
		$prompt .= "Focus on the long-term payoff; what a properly handled {$service} means over the next several years.";
	} else {
		$prompt .= "Explain {$company}'s approach to {$service} in a way that makes the homeowner feel like they understand what to expect and are in good hands.";
	}
	$prompt .= "Two to four concrete sentences. Do not open with \"We handle\" or list service types. Make it feel like a helpful conversation, not a sales pitch. Avoid jargon and fluff.

H2 HEADING — (SEO-friendly. No filler. Standalone tag, not inside a paragraph.)";
	if ( $choose_h2 === 1 ) {
		$prompt .= "Make a short declarative statement pairing {$service} and {$city}";
	} elseif ( $choose_h2 === 2 ) {
		$prompt .= "Pose a question the homeowner would actually ask.";
	} elseif ( $choose_h2 === 3 ) {
		$prompt .= "State the outcome the homeowner gets, not just the service or city.";
	} elseif ( $choose_h2 === 4 ) {
		$prompt .= "Imply timing matters without being pushy (\"Before the Heat Hits\" type angle).";
	} else {
		$prompt .= "Place-focused — names {$city} or the region in a natural, specific way.";
	}

	$prompt .= "\n\nLOCAL PARAGRAPH — ";
	if ( $wiki ) {
		$prompt .="Use the following information about the neighborhoods, geography, climate, and character of {$city} to infuse this paragraph with local SEO value. Do not copy any sentences verbatim:\n\n\"{$wiki}\"\n\n";
	} else {
		$prompt .= "Use your training knowledge about {$city}, {$state}, to describe the regional climate and housing character. ";
	}

	if ( $local_para === 1 ) {
		$prompt .= "Then focus on timing: when does {$service} become urgent in {$city}? What months push systems hardest in this climate?";
	} elseif ( $local_para === 2 ) {
		$prompt .= "Then focus on the homes specifically: what is the housing stock like in {$city}? Age, size, construction type, or neighborhood character.";
	} elseif ( $local_para === 3 ) {
		$prompt .= "Then focus on the climate: how does the weather and climate in this region affect {$service} specifically?";
	} elseif ( $local_para === 4 ) {
		$prompt .= "Focus on the growth of {$city}; new construction vs. older neighborhoods, rapid development, how that creates mixed HVAC needs across the city.";
	} elseif ( $local_para === 5 ) {
		$prompt .= "Focus on a weather extreme specific to the region — ice storms, drought summers, humidity spikes — told as something that happened, not a forecast.";
	} elseif ( $local_para === 6 ) {
		$prompt .= "Focus on what makes {$city} feel like home — the character of the community, how long people tend to stay, and how that investment in their home connects to keeping their HVAC system in good shape.";
	} else {
		$prompt .= "Then focus on energy demands: how does the local climate drive up bills, and what that means practically for homeowners.";
	}

	$prompt .= "\n\nWRAP-UP PARAGRAPH — ";
	if ( $wrap_up_para === 1 ) {
		$prompt .= "Describe what the homeowner experiences during a visit; what gets done, how it's handled, what they walk away with.";
	} elseif ( $wrap_up_para === 2 ) {
		$prompt .= "Keep it simple: what {$company} shows up to do, stated plainly. Short sentences. No jargon.";
	} elseif ( $wrap_up_para === 3 ) {
		$prompt .= "Describe the before and after; what the problem was, what's different when the job is done.";
	} elseif ( $wrap_up_para === 4 ) {
		$prompt .= "Focus on communication; how the homeowner stays informed during the visit, no surprises on scope or price.";
	} else {
		$prompt .= "Talk about follow-through: what the homeowner knows after the job is finished and why it was worth the call.";
	}
	$prompt .= "End with a sentence that leads naturally into: contact us today at <strong>[get-biz info=\"phone-link\"]</strong>!

RULES:
- Every paragraph in <p> tags. H2 is standalone, not inside <p>.
- First-person plural (\"we\", \"our\") throughout except the first sentence of the opening paragraph.
- 6th grade reading level. Mix short and longer sentences.
- 200-260 words total.
- No em-dashes. No: seamless, cutting-edge, state-of-the-art, peace of mind, look no further, dedicated team, expert, trust us.
- Output ONLY the HTML.

Also write a map_caption (~8-12 words, plain text): Should be a concise, natural-sounding sentence. If necessary, use the plural version of {$service}. Example: ";
	if ( $map_caption === 1 ) {
		$prompt .= "{$service} completed by {$company} in {$city}";
	} elseif ( $map_caption === 2 ) {
		$prompt .= "Recent {$service} we completed in {$city}";
	} elseif ( $map_caption === 3 ) {
		$prompt .= "{$company} {$service} projects completed in {$city} area";
	} elseif ( $map_caption === 4 ) {
		$prompt .= "{$service} in {$city} and surrounding areas";
	} else {
		$prompt .= "{$city} homes where {$company} provided {$service} solutions.";
	}
	$prompt .= "No HTML tags.

Respond ONLY with valid JSON: {\"intro\": \"...html content...\", \"map_caption\": \"...plain text...\"}";

	return $prompt;
}
