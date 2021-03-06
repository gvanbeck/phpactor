<?php

namespace Phpactor\Extension\WorseReflection;

use Phpactor\WorseReflection\Core\SourceCodeLocator\NativeReflectionFunctionSourceLocator;
use Phpactor\WorseReflection\Reflector;
use Phpactor\WorseReflection\Bridge\PsrLog\PsrLogger;
use Phpactor\WorseReflection\Core\SourceCodeLocator\StubSourceLocator;
use Phpactor\WorseReflection\Bridge\Phpactor\ClassToFileSourceLocator;
use Phpactor\WorseReflection\ReflectorBuilder;
use Phpactor\Container\Extension;
use Phpactor\MapResolver\Resolver;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Container;
use Phpactor\Extension\WorseReflection\Rpc\GotoDefinitionHandler;
use Phpactor\Extension\WorseReflection\Command\OffsetInfoCommand;
use Phpactor\Extension\WorseReflection\Application\OffsetInfo;
use Phpactor\Extension\WorseReflection\Application\ClassReflector;
use Phpactor\Extension\WorseReflection\Command\ClassReflectorCommand;
use Phpactor\WorseReflection\Bridge\TolerantParser\Parser\CachedParser;
use Phpactor\WorseReflection\Bridge\TolerantParser\Reflector\TolerantFactory;

class WorseReflectionExtension implements Extension
{
    /**
     * {@inheritDoc}
     */
    public function configure(Resolver $schema)
    {
        $schema->setDefaults([
            'reflection.stub_directory' => 'jetbrains/phpstorm-stubs',
        ]);
    }

    public function load(ContainerBuilder $container)
    {
        $this->registerReflection($container);
        $this->registerGotoDefinition($container);
        $this->registerCommands($container);
        $this->registerApplicationServices($container);
    }

    private function registerReflection(ContainerBuilder $container)
    {
        $container->register('reflection.reflector', function (Container $container) {
            $builder = ReflectorBuilder::create()
                ->enableCache()
                ->withSourceReflectorFactory(new TolerantFactory($container->get('reflection.tolerant_parser')))
                ->enableContextualSourceLocation();
        
            foreach (array_keys($container->getServiceIdsForTag('reflection.source_locator')) as $locatorId) {
                $builder->addLocator($container->get($locatorId));
            }
        
            $builder->withLogger(new PsrLogger($container->get('monolog.logger')));
        
            return $builder->build();
        });

        $container->register('reflection.tolerant_parser', function (Container $container) {
            return new CachedParser();
        });
        
        $container->register('reflection.locator.stub', function (Container $container) {
            return new StubSourceLocator(
                ReflectorBuilder::create()->build(),
                $container->getParameter('vendor_dir') . '/' . $container->getParameter('reflection.stub_directory'),
                $container->getParameter('cache_dir')
            );
        }, [ 'reflection.source_locator' => []]);
        
        $container->register('reflection.locator.worse', function (Container $container) {
            return new ClassToFileSourceLocator($container->get('class_to_file.class_to_file'));
        }, [ 'reflection.source_locator' => []]);

        $container->register('reflection.locator.reflection_function', function (Container $container) {
            return new NativeReflectionFunctionSourceLocator();
        }, [ 'reflection.source_locator' => []]);
    }

    private function registerGotoDefinition(ContainerBuilder $container)
    {
        $container->register('rpc.handler.goto_definition', function (Container $container) {
            return new GotoDefinitionHandler(
                $container->get('reflection.reflector')
            );
        }, [ 'rpc.handler' => [] ]);
    }

    private function registerApplicationServices(ContainerBuilder $container)
    {
        $container->register('application.offset_info', function (Container $container) {
            return new OffsetInfo(
                $container->get('reflection.reflector'),
                $container->get('application.helper.class_file_normalizer')
            );
        });
        $container->register('application.class_reflector', function (Container $container) {
            return new ClassReflector(
                $container->get('application.helper.class_file_normalizer'),
                $container->get('reflection.reflector')
            );
        });
    }

    private function registerCommands(ContainerBuilder $container)
    {
        $container->register('command.offset_info', function (Container $container) {
            return new OffsetInfoCommand(
                $container->get('application.offset_info'),
                $container->get('console.dumper_registry')
            );
        }, [ 'ui.console.command' => []]);
        $container->register('command.class_reflector', function (Container $container) {
            return new ClassReflectorCommand(
                $container->get('application.class_reflector'),
                $container->get('console.dumper_registry')
            );
        }, [ 'ui.console.command' => []]);
    }
}
