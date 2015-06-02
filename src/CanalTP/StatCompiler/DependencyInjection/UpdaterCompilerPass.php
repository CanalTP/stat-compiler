<?php
namespace CanalTP\StatCompiler\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class UpdaterCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('updatedb_command')) {
            return;
        }

        $definition = $container->getDefinition(
            'updatedb_command'
        );

        $taggedServices = $container->findTaggedServiceIds(
            'updatedb.updater'
        );

        foreach ($taggedServices as $id => $attributes) {
            $definition->addMethodCall(
                'addUpdater',
                array(new Reference($id))
            );
        }
    }
}