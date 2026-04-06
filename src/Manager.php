<?php

declare(strict_types=1);

namespace yii\inertia;

use Closure;
use Yii;
use yii\base\Component;
use yii\helpers\{ArrayHelper, Json, Url};
use yii\web\{Request, Response};

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
            $response->format = Response::FORMAT_RAW;

            $response->content = '';
            $response->data = null;

            $response->setStatusCode(409);
            $response->getHeaders()->set('X-Inertia-Location', $request->getAbsoluteUrl());
            $this->ensureVaryHeader($response);

            return $response;
        }

        [$errors, $flash] = $this->consumeFlashes();
        [$resolvedProps, $metadata] = $this->resolvePropsAndMetadata($component, $props, $errors, $flash);

        $page = (new Page($component, $resolvedProps, $request->getUrl(), $version))
            ->withFlash($flash)
            ->withDeferredProps($metadata['deferredProps'])
            ->withMergeProps($metadata['mergeProps'])
            ->withPrependProps($metadata['prependProps'])
            ->withDeepMergeProps($metadata['deepMergeProps'])
            ->withMatchPropsOn($metadata['matchPropsOn'])
            ->withScrollProps($metadata['scrollProps'])
            ->withOnceProps($metadata['onceProps']);

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
        if (!Yii::$app->has('session')) {
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
     * Collects deferred-prop metadata and returns the callback when the request is a partial reload.
     *
     * @phpstan-param array{deferredProps: array<string, list<string>>, mergeProps: list<string>, prependProps: list<string>, deepMergeProps: list<string>, matchPropsOn: array<string, string>, scrollProps: array<string, array<string, mixed>>, onceProps: array<string, array<string, mixed>>} $metadata
     *
     * @phpstan-return (Closure(): mixed)|null
     */
    private function handleDeferredProp(
        DeferredProp $prop,
        string $path,
        bool $isPartialReload,
        array &$metadata,
    ): Closure|null {
        $metadata['deferredProps'][$prop->getGroup()][] = $path;

        return $isPartialReload ? $prop->getCallback() : null;
    }

    /**
     * Collects merge-prop metadata unless the prop is being reset by the client.
     *
     * @param list<string> $resetProps Prop paths the client wants to reset.
     *
     * @phpstan-param array{deferredProps: array<string, list<string>>, mergeProps: list<string>, prependProps: list<string>, deepMergeProps: list<string>, matchPropsOn: array<string, string>, scrollProps: array<string, array<string, mixed>>, onceProps: array<string, array<string, mixed>>} $metadata
     */
    private function handleMergeProp(MergeProp $prop, string $path, array $resetProps, array &$metadata): void
    {
        if (in_array($path, $resetProps, true)) {
            return;
        }

        $metadata['mergeProps'][] = $path;

        if ($prop->isDeep()) {
            $metadata['deepMergeProps'][] = $path;
        }

        foreach ($prop->getAppendPaths() as $appendPath => $matchKey) {
            $fullPath = $appendPath !== '' ? $path . '.' . $appendPath : $path;

            if ($matchKey !== '') {
                $metadata['matchPropsOn'][$fullPath] = $matchKey;
            }
        }

        foreach ($prop->getPrependPaths() as $prependPath => $matchKey) {
            $fullPath = $prependPath !== '' ? $path . '.' . $prependPath : $path;
            $metadata['prependProps'][] = $fullPath;

            if ($matchKey !== '') {
                $metadata['matchPropsOn'][$fullPath] = $matchKey;
            }
        }
    }

    /**
     * Collects once-prop metadata and returns the callback, or `null` when the client already has the prop cached.
     *
     * @param list<string> $exceptOnceProps Once-prop keys the client already has cached.
     *
     * @phpstan-param array{deferredProps: array<string, list<string>>, mergeProps: list<string>, prependProps: list<string>, deepMergeProps: list<string>, matchPropsOn: array<string, string>, scrollProps: array<string, array<string, mixed>>, onceProps: array<string, array<string, mixed>>} $metadata
     *
     * @phpstan-return (Closure(): mixed)|null
     */
    private function handleOnceProp(OnceProp $prop, string $path, array $exceptOnceProps, array &$metadata): Closure|null
    {
        $onceKey = $prop->getKey() ?? $path;

        if (in_array($onceKey, $exceptOnceProps, true)) {
            return null;
        }

        /** @phpstan-var array{prop: string, expiresAt?: int} $onceEntry */
        $onceEntry = ['prop' => $path];

        $expiresAt = $prop->getExpiresAtMs();

        if ($expiresAt !== null) {
            $onceEntry['expiresAt'] = $expiresAt;
        }

        $metadata['onceProps'][$onceKey] = $onceEntry;

        return $prop->getCallback();
    }

    /**
     * Invokes a closure passing the current request. Closures that declare no parameters silently ignore it.
     *
     * @param Closure $closure Closure to invoke.
     *
     * @phpstan-param Closure(): mixed|Closure(Request): mixed $closure
     */
    private function invokeClosure(Closure $closure): mixed
    {
        return $closure(Yii::$app->getRequest());
    }

    /**
     * Returns `true` if `$path` is explicitly excluded by the `X-Inertia-Partial-Except` list.
     *
     * Paths under `errors` and `flash` are never excluded, ensuring validation messages and flash data survive partial
     * reloads. Paths marked as always-included are also never excluded.
     *
     * @param string $path Dot-notation prop path.
     * @param list<string> $except Excluded paths from the partial-except header.
     * @param list<string> $alwaysPaths Paths that bypass partial reload filtering.
     *
     * @return bool `true` if the path is explicitly excluded; otherwise, `false`.
     */
    private function isExplicitlyExcluded(string $path, array $except, array $alwaysPaths = []): bool
    {
        if (
            $path === 'errors'
            || str_starts_with($path, 'errors.')
            || $path === 'flash'
            || str_starts_with($path, 'flash.')
        ) {
            return false;
        }

        foreach ($alwaysPaths as $alwaysPath) {
            if ($this->pathStartsWith($path, $alwaysPath) || $this->pathStartsWith($alwaysPath, $path)) {
                return false;
            }
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
     * Paths under `errors` and `flash` always match. Paths marked as always-included also always match. An empty
     * `$only` list means all paths are included.
     *
     * @param string $path Dot-notation prop path.
     * @param list<string> $only Included paths from the partial-data header.
     * @param list<string> $alwaysPaths Paths that bypass partial reload filtering.
     *
     * @return bool `true` if the path matches the inclusion list; otherwise, `false`.
     */
    private function matchesOnly(string $path, array $only, array $alwaysPaths = []): bool
    {
        if (
            $path === 'errors'
            || str_starts_with($path, 'errors.')
            || $path === 'flash'
            || str_starts_with($path, 'flash.')
        ) {
            return true;
        }

        foreach ($alwaysPaths as $alwaysPath) {
            if ($this->pathStartsWith($path, $alwaysPath) || $this->pathStartsWith($alwaysPath, $path)) {
                return true;
            }
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
        if ($value === null || $value === '') {
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
     * Walks the props tree, unwraps v3 prop wrappers, collects page metadata, and separates always-included paths.
     *
     * Returns an array of `[$unwrappedProps, $alwaysPaths]` where closures from wrappers replace the wrapper objects
     * and `$alwaysPaths` lists dot-notation paths that bypass partial reload filtering.
     *
     * @param array $props Props tree to process.
     * @param string $prefix Current dot-notation prefix for nested paths.
     * @param bool $isPartialReload Whether this is a partial reload request.
     * @param list<string> $resetProps Prop paths the client wants to reset (skip merge metadata).
     * @param list<string> $exceptOnceProps Once-prop keys the client already has cached.
     * @param array $metadata Metadata arrays collected by reference.
     *
     * @return array Tuple of `[$unwrappedProps, $alwaysPaths]`.
     *
     * @phpstan-param array<int|string, mixed> $props
     * @phpstan-param array{
     *   deferredProps: array<string, list<string>>,
     *   mergeProps: list<string>,
     *   prependProps: list<string>,
     *   deepMergeProps: list<string>,
     *   matchPropsOn: array<string, string>,
     *   scrollProps: array<string, array<string, mixed>>,
     *   onceProps: array<string, array<string, mixed>>,
     * } $metadata
     *
     * @phpstan-return array{array<int|string, mixed>, list<string>}
     */
    private function preprocessProps(
        array $props,
        string $prefix,
        bool $isPartialReload,
        array $resetProps,
        array $exceptOnceProps,
        array &$metadata,
    ): array {
        $result = [];
        /** @phpstan-var list<string> $alwaysPaths */
        $alwaysPaths = [];

        foreach ($props as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if ($value instanceof DeferredProp) {
                $resolved = $this->handleDeferredProp($value, $path, $isPartialReload, $metadata);

                if ($resolved !== null) {
                    $result[$key] = $resolved;
                }

                continue;
            }

            if ($value instanceof OptionalProp) {
                if ($isPartialReload) {
                    $result[$key] = $value->getCallback();
                }

                continue;
            }

            if ($value instanceof AlwaysProp) {
                $result[$key] = $value->getValue();
                $alwaysPaths[] = $path;

                continue;
            }

            if ($value instanceof MergeProp) {
                $this->handleMergeProp($value, $path, $resetProps, $metadata);
                $result[$key] = $value->getValue();

                continue;
            }

            if ($value instanceof OnceProp) {
                $resolved = $this->handleOnceProp($value, $path, $exceptOnceProps, $metadata);

                if ($resolved !== null) {
                    $result[$key] = $resolved;
                }

                continue;
            }

            if (is_array($value) && $value !== []) {
                [$nested, $nestedAlwaysPaths] = $this->preprocessProps(
                    $value,
                    $path,
                    $isPartialReload,
                    $resetProps,
                    $exceptOnceProps,
                    $metadata,
                );
                $result[$key] = $nested;
                $alwaysPaths = array_merge($alwaysPaths, $nestedAlwaysPaths);

                continue;
            }

            $result[$key] = $value;
        }

        return [$result, $alwaysPaths];
    }

    /**
     * Recursively resolves a single prop node, applying partial-reload include/exclude filtering.
     *
     * @param mixed $value Raw prop value (scalar, array, or Closure).
     * @param string $path Current dot-notation path within the prop tree.
     * @param list<string> $only Included paths from the partial-data header.
     * @param list<string> $except Excluded paths from the partial-except header.
     * @param list<string> $alwaysPaths Paths that bypass partial reload filtering.
     * @param bool $root Whether this is the root node (always included, never filtered).
     *
     * @return array Tuple of `[$include, $resolvedValue]`.
     *
     * @phpstan-return array{bool, mixed}
     */
    private function resolveNode(
        mixed $value,
        string $path,
        array $only,
        array $except,
        array $alwaysPaths = [],
        bool $root = false,
    ): array {
        if (!$root && !$this->matchesOnly($path, $only, $alwaysPaths)) {
            return [false, null];
        }

        if (!$root && $this->isExplicitlyExcluded($path, $except, $alwaysPaths)) {
            return [false, null];
        }

        if ($value instanceof Closure) {
            $value = $this->invokeClosure($value);
        }

        if (is_array($value) && $value !== []) {
            $resolved = [];

            foreach ($value as $key => $child) {
                $childPath = $path === '' ? (string) $key : $path . '.' . $key;
                [$include, $childValue] = $this->resolveNode($child, $childPath, $only, $except, $alwaysPaths);

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
     * Merges shared and page props, preprocesses v3 prop wrappers, and applies partial-reload filtering.
     *
     * @param string $component Frontend component name used to match the partial-reload header.
     * @param array $props Page-level props passed to `render()`.
     * @param array $errors Validation errors extracted from session flashes.
     * @param array $flash Session flash messages extracted from session flashes.
     *
     * @return array Tuple of `[$resolvedProps, $metadata]`.
     *
     * @phpstan-param array<string, mixed> $props
     * @phpstan-param array<string, mixed> $errors
     * @phpstan-param array<string, mixed> $flash
     *
     * @phpstan-return array{
     *   array<string, mixed>,
     *   array{
     *     deferredProps: array<string, list<string>>,
     *     mergeProps: list<string>,
     *     prependProps: list<string>,
     *     deepMergeProps: list<string>,
     *     matchPropsOn: array<string, string>,
     *     scrollProps: array<string, array<string, mixed>>,
     *     onceProps: array<string, array<string, mixed>>,
     *   },
     * }
     */
    private function resolvePropsAndMetadata(string $component, array $props, array $errors, array $flash): array
    {
        $resolved = ArrayHelper::merge($this->shared, $props);

        $resolved['errors'] = $errors;

        if ($flash !== [] || !array_key_exists('flash', $resolved)) {
            $resolved['flash'] = $flash;
        }

        $request = Yii::$app->getRequest();
        $isPartialReload = $this->shouldApplyPartialReload($component);

        $resetProps = $this->parseHeaderList($request->getHeaders()->get('X-Inertia-Reset'));
        $exceptOnceProps = $this->parseHeaderList($request->getHeaders()->get('X-Inertia-Except-Once-Props'));

        /**
         * @phpstan-var array{
         *   deferredProps: array<string, list<string>>,
         *   mergeProps: list<string>,
         *   prependProps: list<string>,
         *   deepMergeProps: list<string>,
         *   matchPropsOn: array<string, string>,
         *   scrollProps: array<string, array<string, mixed>>,
         *   onceProps: array<string, array<string, mixed>>,
         * } $metadata
         */
        $metadata = [
            'deferredProps' => [],
            'mergeProps' => [],
            'prependProps' => [],
            'deepMergeProps' => [],
            'matchPropsOn' => [],
            'scrollProps' => [],
            'onceProps' => [],
        ];

        [$preprocessed, $alwaysPaths] = $this->preprocessProps(
            $resolved,
            '',
            $isPartialReload,
            $resetProps,
            $exceptOnceProps,
            $metadata,
        );

        if (!$isPartialReload) {
            /** @phpstan-var array<string, mixed> $resolvedProps */
            $resolvedProps = $this->resolveValue($preprocessed);

            return [$resolvedProps, $metadata];
        }

        $only = $this->parseHeaderList($request->getHeaders()->get('X-Inertia-Partial-Data'));
        $except = $this->parseHeaderList($request->getHeaders()->get('X-Inertia-Partial-Except'));
        [, $filtered] = $this->resolveNode($preprocessed, '', $only, $except, $alwaysPaths, true);

        /** @phpstan-var array<string, mixed> $filtered */
        return [$filtered, $metadata];
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
