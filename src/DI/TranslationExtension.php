<?php declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Translation\DI;

use Closure;
use Contributte\Console\DI\ConsoleExtension;
use Kdyby\Translation\Caching\PhpFileStorage;
use Kdyby\Translation\CatalogueCompiler;
use Kdyby\Translation\CatalogueFactory;
use Kdyby\Translation\Diagnostics\Panel;
use Kdyby\Translation\FallbackResolver;
use Kdyby\Translation\InvalidResourceException;
use Kdyby\Translation\IUserLocaleResolver;
use Kdyby\Translation\Latte\TranslateMacros;
use Kdyby\Translation\LocaleResolver\AcceptHeaderResolver;
use Kdyby\Translation\LocaleResolver\ChainResolver;
use Kdyby\Translation\LocaleResolver\LocaleParamResolver;
use Kdyby\Translation\LocaleResolver\SessionResolver;
use Kdyby\Translation\TemplateHelpers;
use Kdyby\Translation\TranslationLoader;
use Kdyby\Translation\Translator as KdybyTranslator;
use Latte\Engine as LatteEngine;
use Nette\Application\Application;
use Nette\Bridges\ApplicationLatte\ILatteFactory;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\DI\Helpers;
use Nette\PhpGenerator\ClassType as ClassTypeGenerator;
use Nette\PhpGenerator\PhpLiteral;
use Nette\Reflection\ClassType as ReflectionClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\SmartObject;
use Nette\Utils\Finder;
use Nette\Utils\Validators;
use ReflectionException;
use SplFileInfo;
use Symfony\Component\Translation\Extractor\ChainExtractor;
use Symfony\Component\Translation\Formatter\IntlFormatter;
use Symfony\Component\Translation\Formatter\IntlFormatterInterface;
use Symfony\Component\Translation\Formatter\MessageFormatter;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Writer\TranslationWriter;
use Throwable;
use Tracy\Debugger;
use Tracy\IBarPanel;

class TranslationExtension extends CompilerExtension
{

    use SmartObject;

    /** @deprecated */
    public const LOADER_TAG = self::TAG_LOADER;

    /** @deprecated */
    public const DUMPER_TAG = self::TAG_DUMPER;

    /** @deprecated */
    public const EXTRACTOR_TAG = self::TAG_EXTRACTOR;
    public const TAG_LOADER = 'translation.loader';
    public const TAG_DUMPER = 'translation.dumper';
    public const TAG_EXTRACTOR = 'translation.extractor';
    public const RESOLVER_REQUEST = 'request';
    public const RESOLVER_HEADER = 'header';
    public const RESOLVER_SESSION = 'session';

    /** @var mixed[] */
    public $defaults = [
        'whitelist' => null, // array('cs', 'en'),
        'default' => 'en',
        'logging' => null, //  TRUE for psr/log, or string for kdyby/monolog channel
        // 'fallback' => array('en_US', 'en'), // using custom merge strategy becase Nette's config merger appends lists of values
        'dirs' => ['%appDir%/lang', '%appDir%/locale'],
        'cache' => PhpFileStorage::class,
        'debugger' => '%debugMode%',
        'resolvers' => [
            self::RESOLVER_SESSION => false,
            self::RESOLVER_REQUEST => true,
            self::RESOLVER_HEADER => true,
        ],
        'loaders' => [],
    ];

    /** @var array<int|string,int|string> */
    private $loaders;

    public function getConfigSchema(): Schema
    {
        return Expect::structure([
                'whitelist' => Expect::anyOf(Expect::arrayOf('string'), null),
                'default' => Expect::string('en'),
                'logging' => Expect::anyOf(Expect::string(), Expect::bool()),
                'fallback' => Expect::arrayOf('string')->default(['en_US']),
                'dirs' => Expect::arrayOf('string')->default(['%appDir%/lang', '%appDir%/locale']),
                'cache' => Expect::string(PhpFileStorage::class),
                'debugger' => Expect::bool(false),
                'resolvers' => Expect::array()->default([
                    self::RESOLVER_SESSION => false,
                    self::RESOLVER_REQUEST => true,
                    self::RESOLVER_HEADER => true,
                ]),
                'loaders' => Expect::array(),
            ])->castTo('array');
    }

    public function loadConfiguration()
    {
        $this->loaders = [];

        $builder = $this->getContainerBuilder();
        /** @var array<string,string|array<string>> $config */
        $config = $this->config;
        $dir = Helpers::expand('%tempDir%/cache', $builder->parameters);
        $config['cache'] = new Statement($config['cache'], [dirname(is_string($dir) ? $dir : '')]);

        $translator = $builder->addDefinition($this->prefix('default'))
            ->setFactory(KdybyTranslator::class, [$this->prefix('@userLocaleResolver')])
            ->addSetup('?->setTranslator(?)', [$this->prefix('@userLocaleResolver.param'), '@self'])
            ->addSetup('setDefaultLocale', [$config['default']])
            ->addSetup('setLocaleWhitelist', [$config['whitelist']]);

        Validators::assertField($config, 'fallback', 'list');
        $translator->addSetup('setFallbackLocales', [$config['fallback']]);

        $catalogueCompiler = $builder->addDefinition($this->prefix('catalogueCompiler'))
            ->setFactory(CatalogueCompiler::class, self::filterArgs($config['cache']));

        if ($config['debugger'] && interface_exists(IBarPanel::class)) {
            $appDir = Helpers::expand('%appDir%', $builder->parameters);
            $builder->addDefinition($this->prefix('panel'))
                ->setFactory(Panel::class, [dirname(is_string($appDir) ? $appDir : '')])
                ->addSetup('setLocaleWhitelist', [$config['whitelist']]);

            $translator->addSetup('?->register(?)', [$this->prefix('@panel'), '@self']);
            $catalogueCompiler->addSetup('enableDebugMode');
        }

        $this->loadLocaleResolver($config);

        $builder->addDefinition($this->prefix('helpers'))
            ->setClass(TemplateHelpers::class)
            ->setFactory($this->prefix('@default') . '::createTemplateHelpers');

        $builder->addDefinition($this->prefix('fallbackResolver'))
            ->setClass(FallbackResolver::class);

        $builder->addDefinition($this->prefix('catalogueFactory'))
            ->setClass(CatalogueFactory::class);

        $builder->addDefinition($this->prefix('selector'))
            ->setClass(MessageSelector::class);

        if (interface_exists(IntlFormatterInterface::class)) {
            $builder->addDefinition($this->prefix('intlFormatter'))
                ->setType(IntlFormatterInterface::class)
                ->setFactory(IntlFormatter::class);
        }

        $builder->addDefinition($this->prefix('formatter'))
            ->setType(MessageFormatterInterface::class)
            ->setFactory(MessageFormatter::class);

        $builder->addDefinition($this->prefix('extractor'))
            ->setClass(ChainExtractor::class);

        $this->loadExtractors();

        $builder->addDefinition($this->prefix('writer'))
            ->setClass(TranslationWriter::class);

        $this->loadDumpers();

        $builder->addDefinition($this->prefix('loader'))
            ->setClass(TranslationLoader::class);

        $loaders = $this->loadFromFile(__DIR__ . '/config/loaders.neon');
        /** @var array<string> $cloaders * */
        $cloaders = $config['loaders'] ?: array_keys($loaders);
        $this->loadLoaders($loaders, $cloaders);
    }

    /**
     * @param array<string, (array<string>|Statement|string)> $config
     * @return void
     */
    protected function loadLocaleResolver(array $config): void
    {
        $builder = $this->getContainerBuilder();

        $builder->addDefinition($this->prefix('userLocaleResolver.param'))
            ->setClass(LocaleParamResolver::class)
            ->setAutowired(false);

        $builder->addDefinition($this->prefix('userLocaleResolver.acceptHeader'))
            ->setClass(AcceptHeaderResolver::class);

        $builder->addDefinition($this->prefix('userLocaleResolver.session'))
            ->setClass(SessionResolver::class);

        $chain = $builder->addDefinition($this->prefix('userLocaleResolver'))
            ->setClass(IUserLocaleResolver::class)
            ->setFactory(ChainResolver::class);

        $resolvers = [];
        /** @var array<string, string> $cresolvers * */
        $cresolvers = $config['resolvers'];
        if ($cresolvers[self::RESOLVER_HEADER]) {
            $resolvers[] = $this->prefix('@userLocaleResolver.acceptHeader');
            $chain->addSetup('addResolver', [$this->prefix('@userLocaleResolver.acceptHeader')]);
        }

        if ($cresolvers[self::RESOLVER_REQUEST]) {
            $resolvers[] = $this->prefix('@userLocaleResolver.param');
            $chain->addSetup('addResolver', [$this->prefix('@userLocaleResolver.param')]);
        }

        if ($cresolvers[self::RESOLVER_SESSION]) {
            $resolvers[] = $this->prefix('@userLocaleResolver.session');
            $chain->addSetup('addResolver', [$this->prefix('@userLocaleResolver.session')]);
        }

        if ($config['debugger'] && interface_exists(IBarPanel::class)) {
            /** @var ServiceDefinition $panel */
            $panel = $builder->getDefinition($this->prefix('panel'));
            $panel->addSetup('setLocaleResolvers', [array_reverse($resolvers)]);
        }
    }


    protected function loadDumpers(): void
    {
        $builder = $this->getContainerBuilder();

        foreach ($this->loadFromFile(__DIR__ . '/config/dumpers.neon') as $format => $class) {
            $builder->addDefinition($this->prefix('dumper.' . $format))
                ->setClass($class)
                ->addTag(self::TAG_DUMPER, $format);
        }
    }

    /**
     * @param array<string,string> $loaders
     * @param array<string> $allowed
     */
    protected function loadLoaders(array $loaders, array $allowed): void
    {
        $builder = $this->getContainerBuilder();

        foreach ($loaders as $format => $class) {
            if (array_search($format, $allowed) === false) {
                continue;
            }

            $builder->addDefinition($this->prefix('loader.' . $format))
                ->setClass($class)
                ->addTag(self::TAG_LOADER, $format);
        }
    }

    protected function loadExtractors(): void
    {
        $builder = $this->getContainerBuilder();

        foreach ($this->loadFromFile(__DIR__ . '/config/extractors.neon') as $format => $class) {
            $builder->addDefinition($this->prefix('extractor.' . $format))
                ->setClass($class)
                ->addTag(self::TAG_EXTRACTOR, $format);
        }
    }

    public function beforeCompile(): void
    {
        $builder = $this->getContainerBuilder();
        /** @var array<string, array<string>|string> $config */
        $config = $this->config;

        $registerToLatte = function (FactoryDefinition $def) {
            $def->getResultDefinition()->addSetup('?->onCompile[] = function($engine) { ?::install($engine->getCompiler()); }', ['@self', new PhpLiteral(TranslateMacros::class)]);

            $def->getResultDefinition()
                ->addSetup('addProvider', ['translator', $this->prefix('@default')])
                ->addSetup('addFilter', ['translate', [$this->prefix('@helpers'), 'translateFilterAware']]);
        };

        $latteFactoryService = $builder->getByType(LatteFactory::class) ?: $builder->getByType(ILatteFactory::class);
        if (!$latteFactoryService || !self::isOfType($builder->getDefinition($latteFactoryService)->getClass(), LatteEngine::class)) {
            $latteFactoryService = 'nette.latteFactory';
        }

        if ($builder->hasDefinition($latteFactoryService) && (self::isOfType($builder->getDefinition($latteFactoryService)->getClass(), LatteFactory::class) || self::isOfType($builder->getDefinition($latteFactoryService)->getClass(), ILatteFactory::class))) {
            /** @var FactoryDefinition $lfdef * */
            $lfdef = $builder->getDefinition($latteFactoryService);
            $registerToLatte($lfdef);
        }

        if ($builder->hasDefinition('nette.latte')) {
            /** @var FactoryDefinition $nfdef * */
            $nfdef = $builder->getDefinition('nette.latte');
            $registerToLatte($nfdef);
        }

        $applicationService = $builder->getByType(Application::class) ?: 'application';
        if ($builder->hasDefinition($applicationService)) {

            /** @var ServiceDefinition $applicationServiceDefinition */
            $applicationServiceDefinition = $builder->getDefinition($applicationService);
            $applicationServiceDefinition
                ->addSetup('$service->onRequest[] = ?', [[$this->prefix('@userLocaleResolver.param'), 'onRequest']]);

            if ($config['debugger'] && interface_exists(IBarPanel::class)) {
                $applicationServiceDefinition
                    ->addSetup('$self = $this; $service->onStartup[] = function () use ($self) { $self->getService(?); }', [$this->prefix('default')])
                    ->addSetup('$service->onRequest[] = ?', [[$this->prefix('@panel'), 'onRequest']]);
            }
        }

        if (class_exists(Debugger::class)) {
            Panel::registerBluescreen();
        }

        /** @var ServiceDefinition $extractor */
        $extractor = $builder->getDefinition($this->prefix('extractor'));
        foreach ($builder->findByTag(self::TAG_EXTRACTOR) as $extractorId => $meta) {
            Validators::assert($meta, 'string:2..');

            $extractor->addSetup('addExtractor', [$meta, '@' . $extractorId]);

            $builder->getDefinition($extractorId)->setAutowired(false);
        }

        /** @var ServiceDefinition $writer */
        $writer = $builder->getDefinition($this->prefix('writer'));
        foreach ($builder->findByTag(self::TAG_DUMPER) as $dumperId => $meta) {
            Validators::assert($meta, 'string:2..');

            $writer->addSetup('addDumper', [$meta, '@' . $dumperId]);

            $builder->getDefinition($dumperId)->setAutowired(false);
        }

        $this->loaders = [];
        foreach ($builder->findByTag(self::TAG_LOADER) as $loaderId => $meta) {
            Validators::assert($meta, 'string:2..');
            $builder->getDefinition($loaderId)->setAutowired(false);
            $this->loaders[$meta] = $loaderId;
        }

        /** @var ServiceDefinition $loaderDefinition */
        $loaderDefinition = $builder->getDefinition($this->prefix('loader'));
        $loaderDefinition->addSetup('injectServiceIds', [$this->loaders]);

        foreach ($this->compiler->getExtensions() as $extension) {
            if (!$extension instanceof ITranslationProvider) {
                continue;
            }

            /** @var array<string> $cdir * */
            $cdir = $config['dirs'];
            $config['dirs'] = array_merge($cdir, array_values($extension->getTranslationResources()));
        }

        /** @var array<string> $cdir * */
        $cdir = $config['dirs'];
        $config['dirs'] = array_map(function ($dir) use ($builder) {
            $dir = Helpers::expand($dir, $builder->parameters);
            return str_replace((DIRECTORY_SEPARATOR === '/') ? '\\' : '/', DIRECTORY_SEPARATOR, is_string($dir) ? $dir : '');
        }, $cdir);

        $dirs = array_values(array_filter($config['dirs'], Closure::fromCallable('is_dir')));
        if (count($dirs) > 0) {
            foreach ($dirs as $dir) {
                $builder->addDependency($dir);
            }

            $this->loadResourcesFromDirs($dirs);
        }
    }

    /**
     * @param array<string> $dirs
     * @return void
     */
    protected function loadResourcesFromDirs(array $dirs): void
    {
        $builder = $this->getContainerBuilder();
        /** @var array<string, array<string>|string> $config */
        $config = $this->config;
        /** @var array<string> $whitelist */
        $whitelist = $config['whitelist'];
        $whitelistRegexp = KdybyTranslator::buildWhitelistRegexp($whitelist);
        /** @var ServiceDefinition $translator */
        $translator = $builder->getDefinition($this->prefix('default'));

        $mask = array_map(function ($value) {
            return '*.*.' . $value;
        }, array_keys($this->loaders));

        foreach (Finder::findFiles($mask)->from($dirs) as $file) {
            /** @var SplFileInfo $file */
            if (!preg_match('~^(?P<domain>.*?)\.(?P<locale>[^\.]+)\.(?P<format>[^\.]+)$~', $file->getFilename(), $m)) {
                continue;
            }

            if ($whitelistRegexp && !preg_match($whitelistRegexp, $m['locale']) && $builder->parameters['productionMode']) {
                continue; // ignore in production mode, there is no need to pass the ignored resources
            }

            $this->validateResource($m['format'], $file->getPathname(), $m['locale'], $m['domain']);
            $translator->addSetup('addResource', [$m['format'], $file->getPathname(), $m['locale'], $m['domain']]);
            $builder->addDependency($file->getPathname());
        }
    }

    /**
     * @param string $format
     * @param string $file
     * @param string $locale
     * @param string $domain
     */
    protected function validateResource(string $format, string $file, string $locale, string $domain): void
    {
        $builder = $this->getContainerBuilder();

        if (!isset($this->loaders[$format])) {
            return;
        }

        try {
            /** @var ServiceDefinition $def */
            $def = $builder->getDefinition((string) $this->loaders[$format]);
            $refl = ReflectionClassType::from($def->getEntity() ?: $def->getClass());
            $method = $refl->getConstructor();
            if ($method !== null && $method->getNumberOfRequiredParameters() > 1) {
                return;
            }

            $loader = $refl->newInstance();
            if (!$loader instanceof LoaderInterface) {
                return;
            }
        } catch (ReflectionException $e) {
            return;
        }

        try {
            $loader->load($file, $locale, $domain);
        } catch (Throwable $e) {
            throw new InvalidResourceException(sprintf('Resource %s is not valid and cannot be loaded.', $file), 0, $e);
        }
    }

    public function afterCompile(ClassTypeGenerator $class): void
    {
        $initialize = $class->getMethod('initialize');
        if (class_exists(Debugger::class)) {
            $initialize->addBody('?::registerBluescreen();', [new PhpLiteral(Panel::class)]);
        }
    }

    /**
     * @return bool
     */
    private function isRegisteredConsoleExtension(): bool
    {
        foreach ($this->compiler->getExtensions() as $extension) {
            if ($extension instanceof ConsoleExtension) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Configurator $configurator
     */
    public static function register(Configurator $configurator): void
    {
        $configurator->onCompile[] = function ($config, Compiler $compiler) {
            $compiler->addExtension('translation', new TranslationExtension());
        };
    }

    /**
     * @param array<string>|Statement|string $statement
     * @return array<string>
     */
    protected static function filterArgs(array|Statement|string $statement): array
    {
        return Helpers::filterArguments([is_string($statement) ? new Statement($statement) : $statement]);
    }

    /**
     * @param ?string $class
     * @param string $type
     * @return bool
     */
    private static function isOfType(?string $class, string $type): bool
    {
        return $class !== null && ($class === $type || is_subclass_of($class, $type));
    }

}
