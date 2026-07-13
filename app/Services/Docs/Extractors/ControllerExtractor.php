<?php

namespace App\Services\Docs\Extractors;

use Illuminate\Foundation\Http\FormRequest;
use ReflectionMethod;
use ReflectionNamedType;

class ControllerExtractor
{
    public function __construct(private RouteExtractor $routes) {}

    /**
     * Group every routed controller action by controller class.
     *
     * @return array<string, list<array{
     *     method: string,
     *     signature: string,
     *     returnType: ?string,
     *     summary: ?string,
     *     requestBody: list<array{field: string, rules: string}>,
     *     responses: list<array{status: string, body: string}>,
     * }>>
     */
    public function extract(): array
    {
        $grouped = [];

        foreach ($this->routes->uniqueControllerActions() as $pair) {
            if (! class_exists($pair['controller']) || ! method_exists($pair['controller'], $pair['method'])) {
                continue;
            }

            $reflection = new ReflectionMethod($pair['controller'], $pair['method']);
            $source = $this->methodSource($reflection);

            $grouped[$pair['controller']][] = [
                'method' => $pair['method'],
                'signature' => $this->signature($reflection),
                'returnType' => $this->typeName($reflection->getReturnType()),
                'summary' => $this->docSummary($reflection),
                'requestBody' => $this->extractRequestBody($reflection, $source),
                'responses' => $source ? $this->extractResponses($source) : [],
            ];
        }

        foreach ($grouped as &$methods) {
            usort($methods, fn ($a, $b) => $a['method'] <=> $b['method']);
        }

        ksort($grouped);

        return $grouped;
    }

    private function signature(ReflectionMethod $reflection): string
    {
        $params = array_map(function ($param) {
            $type = $this->typeName($param->getType());
            $name = '$'.$param->getName();

            return $type ? "{$type} {$name}" : $name;
        }, $reflection->getParameters());

        return '('.implode(', ', $params).')';
    }

    private function typeName(?\ReflectionType $type): ?string
    {
        if ($type instanceof ReflectionNamedType) {
            $short = class_exists($type->getName()) ? class_basename($type->getName()) : $type->getName();

            return ($type->allowsNull() && $short !== 'null' ? '?' : '').$short;
        }

        return $type ? (string) $type : null;
    }

    /**
     * First line of the method's PHPDoc summary, if any.
     */
    private function docSummary(ReflectionMethod $reflection): ?string
    {
        $doc = $reflection->getDocComment();
        if (! $doc) {
            return null;
        }

        foreach (explode("\n", $doc) as $line) {
            $line = trim($line, " \t*/");
            if ($line !== '' && ! str_starts_with($line, '@')) {
                return $line;
            }
        }

        return null;
    }

    private function methodSource(ReflectionMethod $reflection): ?string
    {
        $file = $reflection->getFileName();
        if (! $file || ! is_readable($file)) {
            return null;
        }

        $lines = file($file);
        $start = $reflection->getStartLine() - 1;
        $end = $reflection->getEndLine();

        return implode('', array_slice($lines, $start, $end - $start));
    }

    /**
     * Derive the request body two ways, whichever applies:
     *   1. A type-hinted Form Request parameter — instantiate it and read its
     *      rules() (this app's primary pattern; validation lives in Form
     *      Requests, not inline in the controller).
     *   2. An inline `$request->validate([...])` array in the method body.
     *
     * @return list<array{field: string, rules: string}>
     */
    private function extractRequestBody(ReflectionMethod $reflection, ?string $source): array
    {
        $fromFormRequest = $this->extractFormRequestRules($reflection);
        if ($fromFormRequest !== []) {
            return $fromFormRequest;
        }

        if ($source === null) {
            return [];
        }

        $arrayLiteral = $this->findCallArgArray($source, '->validate(');
        if ($arrayLiteral === null) {
            return [];
        }

        $pairs = [];
        foreach ($this->splitTopLevelPairs($arrayLiteral) as [$key, $value]) {
            $pairs[] = ['field' => $key, 'rules' => $value];
        }

        return $pairs;
    }

    /**
     * If any parameter is a Form Request, instantiate it and format its
     * rules() as field => rule-string pairs. Rule objects (Password, Rule::in,
     * etc.) are shown by their class name since they have no literal form.
     *
     * @return list<array{field: string, rules: string}>
     */
    private function extractFormRequestRules(ReflectionMethod $reflection): array
    {
        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();
            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $class = $type->getName();
            if (! class_exists($class) || ! is_subclass_of($class, FormRequest::class)) {
                continue;
            }

            try {
                $rules = (new $class)->rules();
            } catch (\Throwable) {
                return [];
            }

            $pairs = [];
            foreach ($rules as $field => $rule) {
                $pairs[] = ['field' => (string) $field, 'rules' => $this->stringifyRules($rule)];
            }

            return $pairs;
        }

        return [];
    }

    /**
     * Turn a rule definition (string, or array of strings and rule objects)
     * into a readable `a|b|c` string.
     */
    private function stringifyRules(mixed $rule): string
    {
        $parts = is_array($rule) ? $rule : [$rule];

        return implode('|', array_map(function ($part) {
            if (is_string($part)) {
                return $part;
            }
            if (is_object($part)) {
                return class_basename($part);
            }

            return (string) $part;
        }, $parts));
    }

    /**
     * Pull every `response()->json([...], $code)` call out of the method
     * source as a labeled example block.
     *
     * @return list<array{status: string, body: string}>
     */
    private function extractResponses(string $source): array
    {
        $responses = [];
        $offset = 0;

        while (($marker = strpos($source, 'response()->json(', $offset)) !== false) {
            $openBracket = strpos($source, '[', $marker);
            $callCloseParen = $this->matchingParen($source, strpos($source, '(', $marker + strlen('response()->json')));

            if ($openBracket === false || $callCloseParen === false || $openBracket > $callCloseParen) {
                $offset = $marker + 1;

                continue;
            }

            $closeBracket = $this->matchingBracket($source, $openBracket);
            if ($closeBracket === false) {
                $offset = $marker + 1;

                continue;
            }

            $body = trim(substr($source, $openBracket + 1, $closeBracket - $openBracket - 1));
            $statusArg = trim(substr($source, $closeBracket + 1, $callCloseParen - $closeBracket - 1), " \t\n,");

            $responses[] = [
                'status' => $statusArg !== '' ? $statusArg : '200 (implicit)',
                'body' => $this->formatArrayAsJsonish($body),
            ];

            $offset = $callCloseParen + 1;
        }

        return $responses;
    }

    /**
     * Find the array literal passed as the first argument to a call like
     * "->validate(" and return its raw inner text.
     */
    private function findCallArgArray(string $source, string $marker): ?string
    {
        $markerPos = strpos($source, $marker);
        if ($markerPos === false) {
            return null;
        }

        $openBracket = strpos($source, '[', $markerPos);
        if ($openBracket === false) {
            return null;
        }

        $closeBracket = $this->matchingBracket($source, $openBracket);
        if ($closeBracket === false) {
            return null;
        }

        return substr($source, $openBracket + 1, $closeBracket - $openBracket - 1);
    }

    /**
     * Given the position of an opening `[`, find the position of its
     * matching `]`, respecting nested brackets and string literals.
     */
    private function matchingBracket(string $source, int $openPos): int|false
    {
        return $this->matchingDelimiter($source, $openPos, '[', ']');
    }

    private function matchingParen(string $source, int|false $openPos): int|false
    {
        if ($openPos === false) {
            return false;
        }

        return $this->matchingDelimiter($source, $openPos, '(', ')');
    }

    private function matchingDelimiter(string $source, int $openPos, string $open, string $close): int|false
    {
        $depth = 0;
        $len = strlen($source);
        $inString = null; // ' or " while inside a string literal

        for ($i = $openPos; $i < $len; $i++) {
            $char = $source[$i];

            if ($inString !== null) {
                if ($char === '\\') {
                    $i++; // skip escaped char
                } elseif ($char === $inString) {
                    $inString = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = $char;

                continue;
            }

            if ($char === $open) {
                $depth++;
            } elseif ($char === $close) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return false;
    }

    /**
     * Split an array literal's inner text into top-level "key => value"
     * pairs, respecting nested brackets/parens/quotes.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function splitTopLevelPairs(string $arrayLiteral): array
    {
        $entries = $this->splitTopLevel($arrayLiteral, ',');
        $pairs = [];

        foreach ($entries as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            $arrowPos = $this->findTopLevel($entry, '=>');
            if ($arrowPos === false) {
                continue;
            }

            $key = trim(substr($entry, 0, $arrowPos), " \t'\"");
            $value = trim(substr($entry, $arrowPos + 2));
            $pairs[] = [$key, $value];
        }

        return $pairs;
    }

    /**
     * @return list<string>
     */
    private function splitTopLevel(string $text, string $delimiter): array
    {
        $parts = [];
        $depth = 0;
        $inString = null;
        $buffer = '';
        $len = strlen($text);

        for ($i = 0; $i < $len; $i++) {
            $char = $text[$i];

            if ($inString !== null) {
                $buffer .= $char;
                if ($char === '\\') {
                    $i++;
                    if ($i < $len) {
                        $buffer .= $text[$i];
                    }
                } elseif ($char === $inString) {
                    $inString = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = $char;
                $buffer .= $char;

                continue;
            }

            if (in_array($char, ['[', '('], true)) {
                $depth++;
            } elseif (in_array($char, [']', ')'], true)) {
                $depth--;
            }

            if ($depth === 0 && $char === $delimiter) {
                $parts[] = $buffer;
                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $parts[] = $buffer;
        }

        return $parts;
    }

    private function findTopLevel(string $text, string $needle): int|false
    {
        $depth = 0;
        $inString = null;
        $len = strlen($text);
        $needleLen = strlen($needle);

        for ($i = 0; $i < $len; $i++) {
            $char = $text[$i];

            if ($inString !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $inString) {
                    $inString = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = $char;

                continue;
            }

            if (in_array($char, ['[', '('], true)) {
                $depth++;
            } elseif (in_array($char, [']', ')'], true)) {
                $depth--;
            }

            if ($depth === 0 && substr($text, $i, $needleLen) === $needle) {
                return $i;
            }
        }

        return false;
    }

    /**
     * Render a raw PHP array literal as a readable JSON-ish block. This is
     * static extraction from source, not a runtime value — expressions are
     * shown verbatim rather than fabricated as resolved data.
     */
    private function formatArrayAsJsonish(string $arrayLiteral): string
    {
        $pairs = $this->splitTopLevelPairs($arrayLiteral);
        if ($pairs === []) {
            return trim($arrayLiteral) !== '' ? trim($arrayLiteral) : '{}';
        }

        $lines = array_map(fn ($pair) => "  \"{$pair[0]}\": {$this->jsonifyValue($pair[1])}", $pairs);

        return "{\n".implode(",\n", $lines)."\n}";
    }

    /**
     * A simple single-quoted PHP string literal is shown as a double-quoted
     * JSON string. Anything else (variables, calls, nested arrays) is a real
     * PHP expression and is left exactly as written — never fabricated.
     */
    private function jsonifyValue(string $value): string
    {
        if (preg_match('/^\'((?:[^\'\\\\]|\\\\.)*)\'$/', $value, $matches)) {
            $unescaped = str_replace(["\\'", '\\\\'], ["'", '\\'], $matches[1]);

            return json_encode($unescaped);
        }

        return $value;
    }
}
