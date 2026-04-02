<?php

declare(strict_types=1);

namespace yii\inertia;

use Closure;
use ReflectionFunction;
use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\Request;
use yii\web\Response;

use function is_array;
use function is_int;
use function is_string;

/**
 * Server-side Inertia response manager registered as the `inertia` application component.
 *
 * Usage example:
 *
 * ```php
 * // config/web.php
 * return [
 *     'bootstrap' => [
 *         \yii\inertia\Bootstrap::class,
 *     ],
 *     'components' => [
 *         'inertia' => [
 *             'class' => \yii\inertia\Manager::class,
 *             'rootView' => '@app/views/app.php',
 *             'version' => static function (): string {
 *                 $path = \Yii::getAlias('@webroot/js/app.js');
 *
 *                 return is_file($path) ? (string) filemtime($path) : '';
 *             },
 *         ],
 *     ],
 * ];
 * ```
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 0.1
 */
final class Manager extends Component
{
    /**
     * Session flash key exposed as `props.errors` in every page payload.
     */
    public string $errorFlashKey = 'errors';
    /**
     * Root element DOM `id` used by the default root view.
     */
    public string $id = 'app';
    /**
     * Root view file rendered for the initial HTML response.
     */
    public string $rootView = '@inertia/views/app.php';
    /**
     * @phpstan-var array<string, mixed> Shared props applied to every rendered page in the current request.
     */
    public array $shared = [];
    /**
     * @phpstan-var (Closure(): (int|string|null))|(Closure(Request): (int|string|null))|int|string|null Asset version
     * used for client-side mismatch detection via the `version` page field.
     */
    public Closure|int|string|null $version = null;

    /**
     * Removes all shared props.
     */
    public function flushShared(): void
    {
        $this->shared = [];
    }

    /**
     * Returns the shared props or the nested value at `$key`.
     *
     * Usage example:
     *
     * ```php
     * // return all shared props.
     * $all = \yii\inertia\Inertia::getShared();
     *
     * // return a nested value using dot notation.
     * $name = \yii\inertia\Inertia::getShared('auth.user.name');
     * ```
     *
     * @param string|null $key Dot-notation key to retrieve, or `null` to return all shared props.
     * @param mixed $default Value returned when `$key` is not found.
     *
     * @return mixed Shared value at `$key`, or `$default` when the key does not exist.
     */
    public function getShared(string|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->shared;
        }

        return ArrayHelper::getValue($this->shared, $key, $default);
    }

    /**
     * Returns the resolved asset version.
     *
     * Usage example:
     *
     * ```php
     * $version = \yii\inertia\Inertia::getVersion();
     * ```
     *
     * @return int|string Resolved version, or an empty `string` when none is configured.
     */
    public function getVersion(): int|string
    {
        $version = $this->version;

        if ($version instanceof Closure) {
            $version = $this->invokeClosure($version);
        }

        if (is_int($version)) {
            return $version;
        }

        if (is_string($version)) {
            return $version;
        }

        return '';
    }

    /**
     * Returns `true` if `$request` (or the current application request) carries the `X-Inertia` header.
     *
     * Usage example:
     *
     * ```php
     * if (\yii\inertia\Inertia::isInertiaRequest()) {
     *    // handle Inertia request...
     * }
     * ```
     *
     * @param Request|null $request Request to check, or `null` to use the current application request.
     *
     * @return bool `true` if the request is Inertia-driven; otherwise, `false`.
     */
    public function isInertiaRequest(Request|null $request = null): bool
    {
        $request ??= Yii::$app->getRequest();

        return strcasecmp($request->getHeaders()->get('X-Inertia', ''), 'true') === 0;
    }

    /**
     * Returns a `409` Inertia location response for Inertia requests, or a standard `302` redirect otherwise.
     *
     * Usage example:
     *
     * ```php
     * return \yii\inertia\Inertia::location(['site/index']);
     * ```
     *
     * @param array|string $url Destination URL or route array accepted by `Url::to()`.
     *
     * @return Response Inertia location response or standard redirect response.
     *
     * @phpstan-param array<string, mixed>|string $url
     */
    public function location(array|string $url): Response
    {
        $response = Yii::$app->getResponse();

        if ($this->isInertiaRequest()) {
            $response->format = Response::FORMAT_RAW;

            $response->content = '';
            $response->data = null;

            $response->setStatusCode(409);
            $response->getHeaders()->set('X-Inertia-Location', Url::to($url, true));

            $this->ensureVaryHeader($response);

            return $response;
        }

        return $response->redirect($url, 302, false);
    }

    /**
     * Renders the given Inertia page component and returns the appropriate response.
     *
     * Usage example:
     *
     * ```php
     * return \yii\inertia\Inertia::render(
     *     'Dashboard',
     *     [
     *         'user' => [
     *             'name' => Yii::$app->getUser()->getIdentity()->getName(),
     *         ],
     *     ],
     * );
     * ```
     *
     * @param string $component Frontend component name.
     * @param array $props Props forwarded to the frontend component.
     * @param array $viewData Additional data available in the root view template only; not sent to the frontend.
     *
     * @return Response Inertia response or standard HTML response.
     *
     * @phpstan-param array<string, mixed> $props
     * @phpstan-param array<string, mixed> $viewData
     */
    public function render(string $component, array $props = [], array $viewData = []): Response
    {
        $request = Yii::$app->getRequest();
        $response = Yii::$app->getResponse();

        $version = $this->getVersion();

        if ($this->shouldReturnVersionConflict($request, $version)) {
            $this->reflashSession();

            $response->format = Response::FORMAT_RAW;

            $response->content = '';
            $response->data = null;

            $response->setStatusCode(409);
            $response->getHeaders()->set('X-Inertia-Location', $request->getAbsoluteUrl());

            $this->ensureVaryHeader($response);

            return $response;
        }

        [$errors, $flash] = $this->consumeFlashes();
        $resolvedProps = $this->resolveProps($component, $props, $errors);

        $page = new Page($component, $resolvedProps, $request->getUrl(), $version, $flash);

        $this->ensureVaryHeader($response);

        if ($this->isInertiaRequest($request)) {
            $response->format = Response::FORMAT_JSON;

            $response->content = null;
            $response->data = $page;

            $response->getHeaders()->set('X-Inertia', 'true');

            return $response;
        }

        $response->format = Response::FORMAT_HTML;

        $response->data = null;

        $response->content = Yii::$app->getView()->renderFile(
            $this->rootView,
            [
                ...$viewData,
                'id' => $this->id,
                'page' => $page,
                'pageJson' => Json::htmlEncode($page),
            ],
        );

        return $response;
    }

    /**
     * Registers props shared with every subsequent Inertia response in the current request.
     *
     * Usage example:
     *
     * ```php
     * // share a single value.
     * \yii\inertia\Inertia::share(
     *     'auth.user',
     *     [
     *         'name' => Yii::$app->getUser()->getIdentity()->getName
     *     ],
     * );
     *
     * // share multiple values at once.
     * \yii\inertia\Inertia::share(
     *     [
     *         'auth.user' => [
     *             'name' => Yii::$app->getUser()->getIdentity()->getName
     *         ],
     *         'flash' => Yii::$app->getSession()->getFlash('message'),
     *     ],
     * );
     * ```
     *
     * @param array|string $key Dot-notation key or an array of key-value pairs to share.
     * @param mixed $value Value to share; ignored when `$key` is an array.
     *
     * @phpstan-param array<string, mixed>|string $key
     */
    public function share(array|string $key, mixed $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $name => $item) {
                // @phpstan-ignore assign.propertyType
                ArrayHelper::setValue($this->shared, $name, $item);
            }

            return;
        }

        // @phpstan-ignore assign.propertyType
        ArrayHelper::setValue($this->shared, $key, $value);
    }

    /**
     * Consumes session flashes and separates error data from general flash messages.
     *
     * @return array Tuple of `[$errors, $flash]`.
     *
     * @phpstan-return array{array<string, mixed>, array<string, mixed>}
     */
    private function consumeFlashes(): array
    {
        if (!Yii::$app->has('session', true)) {
            return [[], []];
        }

        /** @phpstan-var array<string, mixed> $flashes */
        $flashes = Yii::$app->getSession()->getAllFlashes(true);

        $errors = [];

        if (array_key_exists($this->errorFlashKey, $flashes)) {
            /** @phpstan-var array<string, mixed> $errors */
            $errors = (array) $flashes[$this->errorFlashKey];

            unset($flashes[$this->errorFlashKey]);
        }

        return [$errors, $flashes];
    }

    /**
     * Appends `X-Inertia` to the `Vary` response header if not already present.
     *
     * @param Response $response Response whose headers will be modified.
     */
    private function ensureVaryHeader(Response $response): void
    {
        $vary = $response->getHeaders()->get('Vary');

        if ($vary === null || trim($vary) === '') {
            $response->getHeaders()->set('Vary', 'X-Inertia');

            return;
        }

        $tokens = array_map(
            static fn(string $token): string => strtolower(trim($token)),
            explode(',', $vary),
        );

        if (!in_array('x-inertia', $tokens, true)) {
            $response->getHeaders()->set('Vary', $vary . ', X-Inertia');
        }
    }

    /**
     * Invokes a closure with zero arguments or with the current request as the single argument, depending on its
     * signature.
     *
     * @param Closure $closure Closure to invoke.
     *
     * @phpstan-param Closure(): mixed|Closure(Request): mixed $closure
     */
    private function invokeClosure(Closure $closure): mixed
    {
        $reflection = new ReflectionFunction($closure);

        if ($reflection->getNumberOfRequiredParameters() === 0) {
            return $closure();
        }

        return $closure(Yii::$app->getRequest());
    }

    /**
     * Returns `true` if `$path` is explicitly excluded by the `X-Inertia-Partial-Except` list.
     *
     * Paths under `errors` are never excluded, ensuring validation messages survive partial reloads.
     *
     * @param string $path Dot-notation prop path.
     * @param list<string> $except Excluded paths from the partial-except header.
     *
     * @return bool `true` if the path is explicitly excluded; otherwise, `false`.
     */
    private function isExplicitlyExcluded(string $path, array $except): bool
    {
        if ($path === 'errors' || str_starts_with($path, 'errors.')) {
            return false;
        }

        foreach ($except as $candidate) {
            if ($this->pathStartsWith($path, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns `true` if `$path` matches the `X-Inertia-Partial-Data` inclusion list.
     *
     * Paths under `errors` always match. An empty `$only` list means all paths are included.
     *
     * @param string $path Dot-notation prop path.
     * @param list<string> $only Included paths from the partial-data header.
     *
     * @return bool `true` if the path matches the inclusion list; otherwise, `false`.
     */
    private function matchesOnly(string $path, array $only): bool
    {
        if ($path === 'errors' || str_starts_with($path, 'errors.')) {
            return true;
        }

        if ($only === []) {
            return true;
        }

        foreach ($only as $candidate) {
            if ($this->pathStartsWith($path, $candidate) || $this->pathStartsWith($candidate, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parses a comma-separated header value into a trimmed list of non-empty strings.
     *
     * @param string|null $value Raw header value, or `null` when the header is absent.
     *
     * @return array List of parsed items, or an empty `array` when the input is `null` or contains only whitespace.
     *
     * @phpstan-return list<string>
     */
    private function parseHeaderList(string|null $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(
            array_filter(
                array_map('trim', explode(',', $value)),
                static fn(string $item): bool => $item !== '',
            ),
        );
    }

    /**
     * Returns `true` if `$path` equals `$candidate` or is a child of it in dot notation.
     *
     * @param string $path Dot-notation path to test.
     * @param string $candidate Dot-notation prefix to compare against.
     *
     * @return bool `true` if `$path` equals or is a child of `$candidate`; otherwise, `false`.
     */
    private function pathStartsWith(string $path, string $candidate): bool
    {
        return $path === $candidate || str_starts_with($path, $candidate . '.');
    }

    /**
     * Re-sets all current session flashes so they survive a version-conflict redirect.
     */
    private function reflashSession(): void
    {
        if (!Yii::$app->has('session', true)) {
            return;
        }

        foreach (Yii::$app->getSession()->getAllFlashes(true) as $key => $value) {
            Yii::$app->getSession()->setFlash((string) $key, $value);
        }
    }

    /**
     * Recursively resolves a single prop node, applying partial-reload include/exclude filtering.
     *
     * @param mixed $value Raw prop value (scalar, array, or Closure).
     * @param string $path Current dot-notation path within the prop tree.
     * @param list<string> $only Included paths from the partial-data header.
     * @param list<string> $except Excluded paths from the partial-except header.
     * @param bool $root Whether this is the root node (always included, never filtered).
     *
     * @return array Tuple of `[$include, $resolvedValue]`.
     *
     * @phpstan-return array{bool, mixed}
     */
    private function resolveNode(mixed $value, string $path, array $only, array $except, bool $root = false): array
    {
        if (!$root && !$this->matchesOnly($path, $only)) {
            return [false, null];
        }

        if (!$root && $this->isExplicitlyExcluded($path, $except)) {
            return [false, null];
        }

        if ($value instanceof Closure) {
            $value = $this->invokeClosure($value);
        }

        if (is_array($value) && $value !== []) {
            $resolved = [];

            foreach ($value as $key => $child) {
                $childPath = $path === '' ? (string) $key : $path . '.' . $key;
                [$include, $childValue] = $this->resolveNode($child, $childPath, $only, $except);

                if ($include) {
                    $resolved[$key] = $childValue;
                }
            }

            if ($root || $resolved !== [] || $only !== []) {
                return [true, $resolved];
            }

            return [false, null];
        }

        return [true, $value];
    }

    /**
     * Merges shared and page props, injects errors, and applies partial-reload filtering when needed.
     *
     * @param string $component Frontend component name used to match the partial-reload header.
     * @param array $props Page-level props passed to `render()`.
     * @param array $errors Validation errors extracted from session flashes.
     *
     * @return array Resolved props ready to be sent to the client, with all closures invoked and filtered according to
     * the partial reload headers when applicable.
     *
     * @phpstan-param array<string, mixed> $props
     * @phpstan-param array<string, mixed> $errors
     *
     * @phpstan-return array<string, mixed>
     */
    private function resolveProps(string $component, array $props, array $errors): array
    {
        $resolved = ArrayHelper::merge($this->shared, $props);

        $resolved['errors'] = $errors;

        if (!$this->shouldApplyPartialReload($component)) {
            /** @phpstan-var array<string, mixed> */
            return $this->resolveValue($resolved);
        }

        $request = Yii::$app->getRequest();

        $only = $this->parseHeaderList($request->getHeaders()->get('X-Inertia-Partial-Data'));
        $except = $this->parseHeaderList($request->getHeaders()->get('X-Inertia-Partial-Except'));
        [, $filtered] = $this->resolveNode($resolved, '', $only, $except, true);

        /** @phpstan-var array<string, mixed> */
        return $filtered;
    }

    /**
     * Recursively resolves closures within a prop value without applying partial-reload filtering.
     *
     * @param mixed $value Raw prop value (scalar, `array`, or Closure).
     *
     * @return mixed Fully resolved value with all closures invoked.
     */
    private function resolveValue(mixed $value): mixed
    {
        if ($value instanceof Closure) {
            $value = $this->invokeClosure($value);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->resolveValue($item);
            }
        }

        return $value;
    }

    /**
     * Returns `true` if the current request is a partial reload targeting `$component`.
     *
     * @param string $component Frontend component name to match against the `X-Inertia-Partial-Component` header.
     */
    private function shouldApplyPartialReload(string $component): bool
    {
        $request = Yii::$app->getRequest();

        if (!$this->isInertiaRequest($request)) {
            return false;
        }

        if ($request->getHeaders()->get('X-Inertia-Partial-Component') !== $component) {
            return false;
        }

        return $request->getHeaders()->has('X-Inertia-Partial-Data')
            || $request->getHeaders()->has('X-Inertia-Partial-Except');
    }

    /**
     * Returns `true` if the request is an Inertia GET with a mismatched `X-Inertia-Version` header.
     *
     * @param Request $request Current web request.
     * @param int|string $version Resolved asset version to compare against.
     *
     * @return bool `true` if the request should receive a version conflict response; otherwise, `false`.
     */
    private function shouldReturnVersionConflict(Request $request, int|string $version): bool
    {
        if (!$this->isInertiaRequest($request) || !$request->getIsGet()) {
            return false;
        }

        $requestVersion = $request->getHeaders()->get('X-Inertia-Version');

        if ($requestVersion === null) {
            return false;
        }

        return $requestVersion !== (is_int($version) ? (string) $version : $version);
    }
}
