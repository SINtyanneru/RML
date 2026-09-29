<?php
declare(strict_types=1);

enum RMOType {
	case Char;
	case NextLine;
	case FormatItalic;
	case FormatBold;
	case FormatEmphasis;
	case FormatStrike;
	case FormatUnderLine;
	case URL;
	case Emoji;
	case InlineCode;
	case CodeBlock;
	case Image;
	case Video;
	case Header;
	case HR;
	case Note;
	case Warning;
	case List;
}

readonly class DecodedRMO {
	public function __construct(
		public RMOType $type,
		public null|string|RMOHeader|RMO_URL_Image_Video|RMOCodeBlock $data
	) {}
}

readonly class RMOHeader {
	public function __construct(
		public int $level,
		public string $text
	) {}
}

readonly class RMO_URL_Image_Video {
	public function __construct(
		public string $alt,
		public string $url
	) {}
}

readonly class RMOCodeBlock {
	public function __construct(
		public string $language,
		public string $code
	) {}
}

function __array_starts_with(array $char_list, int $i, string $needle): bool {
	$needle_chars = mb_str_split($needle);
	$needle_len = count($needle_chars);
	
	foreach ($needle_chars as $j => $nc) {
		if (!isset($char_list[$i + $j]) || $char_list[$i + $j] !== $nc) {
			return false;
		}
	}
	
	return true;
}

function __rml_parse_line(string $line): array {
	$rmo_list = [];

	$char_list = mb_str_split($line);
	for ($i = 0; $i < count($char_list); $i++) { 
		if ($char_list[$i] === "\\") {
			$i += 1;
			$rmo_list[] = new DecodedRMO(RMOType::Char, $char_list[$i]);
			continue;
		}

		if (__array_starts_with($char_list, $i, "***")) {
			$text = "";
			$i += 3;
			while ($i < count($char_list)) {
				if (__array_starts_with($char_list, $i, "***")) break;
				$text .= $char_list[$i];
				$i += 1;
			}
			$rmo_list[] = new DecodedRMO(RMOType::FormatEmphasis, $text);
			$i += 2;
			continue;
		}

		if (__array_starts_with($char_list, $i, "**")) {
			$text = "";
			$i += 2;
			while ($i < count($char_list)) {
				if (__array_starts_with($char_list, $i, "**")) break;
				$text .= $char_list[$i];
				$i += 1;
			}
			$rmo_list[] = new DecodedRMO(RMOType::FormatBold, $text);
			$i += 1;
			continue;
		}

		if (__array_starts_with($char_list, $i, "*")) {
			$text = "";
			$i += 1;
			while ($i < count($char_list)) {
				if (__array_starts_with($char_list, $i, "*")) break;
				$text .= $char_list[$i];
				$i += 1;
			}
			$rmo_list[] = new DecodedRMO(RMOType::FormatItalic, $text);
			continue;
		}

		if (__array_starts_with($char_list, $i, "~~")) {
			$text = "";
			$i += 2;
			while ($i < count($char_list)) {
				if (__array_starts_with($char_list, $i, "~~")) break;
				$text .= $char_list[$i];
				$i += 1;
			}
			$rmo_list[] = new DecodedRMO(RMOType::FormatStrike, $text);
			$i += 1;
			continue;
		}

		if (__array_starts_with($char_list, $i, "__")) {
			$text = "";
			$i += 2;
			while ($i < count($char_list)) {
				if (__array_starts_with($char_list, $i, "__")) break;
				$text .= $char_list[$i];
				$i += 1;
			}
			$rmo_list[] = new DecodedRMO(RMOType::FormatUnderLine, $text);
			$i += 1;
			continue;
		}

		if (__array_starts_with($char_list, $i, ":")) {
			$name = "";
			$i += 1;
			while ($i < count($char_list)) {
				if (__array_starts_with($char_list, $i, ":")) break;
				$name .= $char_list[$i];
				$i += 1;
			}
			$rmo_list[] = new DecodedRMO(RMOType::Emoji, $name);
			continue;
		}

		if (__array_starts_with($char_list, $i, "[")) {
			$alt = "";
			$url = "";

			//「[」を消費
			$i += 1;

			//----------------------------------------本当にURLか？
			$t = $i;
			$alt_exists = false;
			$url_exists = false;
			while ($t < count($char_list)) {
				if (__array_starts_with($char_list, $t, "]")) {
					//「]」を消費
					$t += 1;
					$alt_exists = true;
					break;
				}
				$t += 1;
			}
			//「(」があるか？
			if (__array_starts_with($char_list, $t, "(")) {
				//「(」を消費
				$t += 1;

				//「)」が来るまで読む
				while ($t < count($char_list)) {
					if (__array_starts_with($char_list, $t, ")")) {
						$url_exists = true;
						break;
					}
					$t += 1;
				}
			}

			if (($alt_exists && $url_exists) == false) {
				$rmo_list[] = new DecodedRMO(RMOType::Char, $char_list[$i]);
				continue;
			}

			//----------------------------------------実際にパース
			//「]」が来るまでaltを読む
			while ($i < count($char_list)) {
				if (__array_starts_with($char_list, $i, "]")) break;
				$alt .= $char_list[$i];
				$i += 1;
			}

			//「]」と「(」を消費
			$i += 2;

			//「)」が来るまでURLを読む
			while ($i < count($char_list)) {
				if (__array_starts_with($char_list, $i, ")")) break;
				$url .= $char_list[$i];
				$i += 1;
			}

			$rmo_list[] = new DecodedRMO(RMOType::URL, new RMO_URL_Image_Video($alt, $url));
			continue;
		}

		$rmo_list[] = new DecodedRMO(RMOType::Char, $char_list[$i]);
	}

	$rmo_list[] = new DecodedRMO(RMOType::NextLine, null);
	return $rmo_list;
}

function rml_parse(string $rml): array {
	$rmo_list = [];

	$line_list = explode("\n", $rml);
	for ($i = 0; $i < count($line_list); $i++) {
		$line = $line_list[$i];

		//エスケープされているなら普通の行として処理
		if (str_starts_with($line, "\\")) {
			$rmo_list = array_merge($rmo_list, __rml_parse_line($line));
			continue;
		}

		//コマンド
		if (str_starts_with($line, "@")) {
			continue;
		}

		//見出し3
		if (str_starts_with($line, "### ")) {
			$rmo_list[] = new DecodedRMO(RMOType::Header, new RMOHeader(3, substr($line, 4)));
			continue;
		}

		//見出し2
		if (str_starts_with($line, "## ")) {
			$rmo_list[] = new DecodedRMO(RMOType::Header, new RMOHeader(2, substr($line, 3)));
			continue;
		}

		//見出し1
		if (str_starts_with($line, "# ")) {
			$rmo_list[] = new DecodedRMO(RMOType::Header, new RMOHeader(1, substr($line, 2)));
			continue;
		}

		//水平線
		if ($line === "___") {
			$rmo_list[] = new DecodedRMO(RMOType::HR, null);
			continue;
		}

		//画像
		if (str_starts_with($line, "![")) {
			$char_list = mb_str_split($line);
			$j = 2;
			$alt = "";
			$url = "";

			//ALT
			while ($j < count($char_list)) {
				$c = $char_list[$j];
				if ($c == "]") break;

				$alt .= $c;

				$j += 1;
			}

			$j += 2;

			//URL
			while ($j < count($char_list)) {
				$c = $char_list[$j];
				if ($c == ")") break;

				$url .= $c;

				$j += 1;
			}

			$rmo_list[] = new DecodedRMO(RMOType::Image, new RMO_URL_Image_Video($alt, $url));

			continue;
		}

		//動画
		if (str_starts_with($line, "?[")) {
			$char_list = mb_str_split($line);
			$j = 2;
			$alt = "";
			$url = "";

			//ALT
			while ($j < count($char_list)) {
				$c = $char_list[$j];
				if ($c == "]") break;

				$alt .= $c;

				$j += 1;
			}

			$j += 2;

			//URL
			while ($j < count($char_list)) {
				$c = $char_list[$j];
				if ($c == ")") break;

				$url .= $c;

				$j += 1;
			}

			$rmo_list[] = new DecodedRMO(RMOType::Video, new RMO_URL_Image_Video($alt, $url));

			continue;
		}

		//コードブロック
		if (str_starts_with($line, "```")) {
			$language = substr($line, 3);
			$code = "";

			$i += 1;
			while ($i < count($line_list)) {
				//ここで破壊したら外のcontinueで$iが消費されるからここで消費する必要はないぜ
				if ($line_list[$i] === "```") break;

				$code .= $line_list[$i]."\n";

				//消費
				$i += 1;
			}

			$rmo_list[] = new DecodedRMO(RMOType::CodeBlock, new RMOCodeBlock($language, $code));

			continue;
		}

		//ノート
		if ($line === ":::") {
			$text = "";

			$i += 1;
			while ($i < count($line_list)) {
				//ここで破壊したら外のcontinueで$iが消費されるからここで消費する必要はないぜ
				if ($line_list[$i] === ":::") break;

				$text .= $line_list[$i]."\n";

				//消費
				$i += 1;
			}

			$rmo_list[] = new DecodedRMO(RMOType::Note, $text);

			continue;
		}

		//警告
		if ($line === "!!!") {
			$text = "";

			$i += 1;
			while ($i < count($line_list)) {
				//ここで破壊したら外のcontinueで$iが消費されるからここで消費する必要はないぜ
				if ($line_list[$i] === "!!!") break;

				$text .= $line_list[$i]."\n";

				//消費
				$i += 1;
			}

			$rmo_list[] = new DecodedRMO(RMOType::Note, $text);

			continue;
		}

		//列挙
		if (str_starts_with($line, "- ")) {
			$rmo_list[] = new DecodedRMO(RMOType::List, substr($line, 2));
			continue;
		}

		//普通の行を処理
		$rmo_list = array_merge($rmo_list, __rml_parse_line($line));
	}

	return $rmo_list;
}

function rml_to_html(string $rml): string {
	$html = "";

	foreach (rml_parse($rml) as $row) {
		$type = $row->type;
		switch ($type) {
			case RMOType::NextLine: {
				$html .= "<BR>";
				break;
			}

			case RMOType::Char: {
				$html .= $row->data;
				break;
			}

			case RMOType::FormatBold: {
				$html .= "<B CLASS=\"RML\">";
				$html .= $row->data;
				$html .= "</B>";
				break;
			}

			case RMOType::FormatItalic: {
				$html .= "<I CLASS=\"RML\">";
				$html .= $row->data;
				$html .= "</I>";
				break;
			}

			case RMOType::FormatEmphasis: {
				$html .= "<MARK CLASS=\"RML\">";
				$html .= $row->data;
				$html .= "</MARK>";
				break;
			}

			case RMOType::FormatStrike: {
				$html .= "<S CLASS=\"RML\">";
				$html .= $row->data;
				$html .= "</S>";
				break;
			}

			case RMOType::FormatUnderLine: {
				$html .= "<U CLASS=\"RML\">";
				$html .= $row->data;
				$html .= "</U>";
				break;
			}

			case RMOType::URL: {
				$html .= "<A CLASS=\"RML\" HREF=\"".$row->data->url."\">".$row->data->alt."</A>";
				break;
			}

			case RMOType::Image: {
				$html .= "<IMG CLASS=\"RML\" SRC=\"".$row->data->url."\" ALT=\"".$row->data->alt."\"><BR>\n";
				break;
			}

			case RMOType::Video: {
				$html .= "<VIDEO CLASS=\"RML\" SRC=\"".$row->data->url."\" ALT=\"".$row->data->alt."\" controls></VIDEO><BR>\n";
				break;
			}

			case RMOType::Emoji: {
				break;
			}

			case RMOType::InlineCode: {
				$html .= "<CODE CLASS=\"RML\">".$row->data."</CODE>";
				break;
			}

			case RMOType::CodeBlock: {
				$html .= "<PRE CLASS=\"RML\" data-language=\"".$row->data->language."\">".$row->data->code."</PRE>\n";
				break;
			}

			case RMOType::Header: {
				$level = $row->data->level;
				$html .= "<H".$level." CLASS=\"RML\">".$row->data->text."</H".$level.">\n";
				break;
			}

			case RMOType::Note: {
				$html .= "<DIV CLASS=\"RML RML_NOTE\">".$row->data."</DIV>";
				break;
			}

			case RMOType::Warning: {
				$html .= "<DIV CLASS=\"RML RML_WARNING\">".$row->data."</DIV>";
				break;
			}

			case RMOType::List: {
				$html .= "・".$row->data."<BR>";
				break;
			}
		}
	}

	return $html;
}

$decoded = rml_to_html(file_get_contents("sample.rml"));
echo $decoded;