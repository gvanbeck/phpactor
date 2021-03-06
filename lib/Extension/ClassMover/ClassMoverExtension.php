<?php

namespace Phpactor\Extension\ClassMover;

use Phpactor\Extension\ClassMover\Application\ClassCopy;
use Phpactor\Extension\ClassMover\Application\ClassMover as ClassMoverApp;
use Phpactor\Extension\ClassMover\Application\ClassReferences;
use Phpactor\ClassMover\Adapter\TolerantParser\TolerantClassFinder;
use Phpactor\ClassMover\Adapter\TolerantParser\TolerantClassReplacer;
use Phpactor\ClassMover\Adapter\WorseTolerant\WorseTolerantMemberFinder;
use Phpactor\ClassMover\Adapter\WorseTolerant\WorseTolerantMemberReplacer;
use Phpactor\ClassMover\ClassMover;
use Phpactor\Extension\ClassMover\Command\ClassCopyCommand;
use Phpactor\Extension\ClassMover\Command\ClassMoveCommand;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\MapResolver\Resolver;
use Phpactor\Container\Container;
use Phpactor\Extension\ClassMover\Command\ReferencesMemberCommand;
use Phpactor\Extension\ClassMover\Command\ReferencesClassCommand;
use Phpactor\Extension\ClassMover\Application\ClassMemberReferences;

class ClassMoverExtension implements Extension
{
    /**
     * {@inheritDoc}
     */
    public function configure(Resolver $schema)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container)
    {
        $this->registerClassMover($container);
        $this->registerApplicationServices($container);
        $this->registerConsoleCommands($container);
    }

    private function registerClassMover(ContainerBuilder $container)
    {
        $container->register('class_mover.class_mover', function (Container $container) {
            return new ClassMover(
                $container->get('class_mover.class_finder'),
                $container->get('class_mover.ref_replacer')
            );
        });

        $container->register('class_mover.class_finder', function (Container $container) {
            return new TolerantClassFinder();
        });

        $container->register('class_mover.member_finder', function (Container $container) {
            return new WorseTolerantMemberFinder(
                $container->get('reflection.reflector')
            );
        });

        $container->register('class_mover.member_replacer', function (Container $container) {
            return new WorseTolerantMemberReplacer();
        });

        $container->register('class_mover.ref_replacer', function (Container $container) {
            return new TolerantClassReplacer();
        });
    }

    private function registerApplicationServices(ContainerBuilder $container)
    {
        $container->register('application.class_mover', function (Container $container) {
            return new ClassMoverApp(
                $container->get('application.helper.class_file_normalizer'),
                $container->get('class_mover.class_mover'),
                $container->get('source_code_filesystem.registry')
            );
        });

        $container->register('application.class_copy', function (Container $container) {
            return new ClassCopy(
                $container->get('application.helper.class_file_normalizer'),
                $container->get('class_mover.class_mover'),
                $container->get('source_code_filesystem.registry')->get('git')
            );
        });

        $container->register('application.class_references', function (Container $container) {
            return new ClassReferences(
                $container->get('application.helper.class_file_normalizer'),
                $container->get('class_mover.class_finder'),
                $container->get('class_mover.ref_replacer'),
                $container->get('source_code_filesystem.registry')
            );
        });

        $container->register('application.method_references', function (Container $container) {
            return new ClassMemberReferences(
                $container->get('application.helper.class_file_normalizer'),
                $container->get('class_mover.member_finder'),
                $container->get('class_mover.member_replacer'),
                $container->get('source_code_filesystem.registry'),
                $container->get('reflection.reflector')
            );
        });
    }

    private function registerConsoleCommands(ContainerBuilder $container)
    {
        $container->register('command.class_move', function (Container $container) {
            return new ClassMoveCommand(
                $container->get('application.class_mover'),
                $container->get('console.prompter')
            );
        }, [ 'ui.console.command' => []]);
        
        $container->register('command.class_copy', function (Container $container) {
            return new ClassCopyCommand(
                $container->get('application.class_copy'),
                $container->get('console.prompter')
            );
        }, [ 'ui.console.command' => []]);
        
        $container->register('command.class_references', function (Container $container) {
            return new ReferencesClassCommand(
                $container->get('application.class_references'),
                $container->get('console.dumper_registry')
            );
        }, [ 'ui.console.command' => []]);
        
        $container->register('command.method_references', function (Container $container) {
            return new ReferencesMemberCommand(
                $container->get('application.method_references'),
                $container->get('console.dumper_registry')
            );
        }, [ 'ui.console.command' => []]);
    }
}
