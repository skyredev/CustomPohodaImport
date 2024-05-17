<?php

use Espo\Core\{
    Container,
    DataManager
};

class AfterUninstall
{
    private DataManager $dataManager;

    public function run(Container $container, array $params = []): void
    {
        $this->loadDependencies($container);
        $this->clearCache();
    }

    private function loadDependencies(Container $container): void
    {
        $this->dataManager = $container->getByClass(DataManager::class);
    }

    private function clearCache(): void
    {
        try {
            $this->dataManager->clearCache();
        } catch (\Exception) {
        }
    }
}
