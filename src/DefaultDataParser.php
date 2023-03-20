<?php

namespace ShopGenerator;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class DefaultDataParser
{
    private XmlEncoder $xmlEncoder;

    public function __construct(
    ) {
        $this->xmlEncoder = new XmlEncoder();
    }

    public function parseDirectory(string $directory): array
    {
        $finder = new Finder();
        $finder->files()->in($directory);

        $data = [];

        foreach ($finder as $file) {
            $entityName = str_replace('.xml', '', $file->getFilename());
            $data[$entityName] = $this->parseFile($file->getPathname());
        }

        return $data;
    }

    public function parseFile(string $filename): array
    {
        $array = $this->xmlEncoder->decode(file_get_contents($filename), 'xml', [
            'as_collection' => true,
        ]);

        // fields and entites are not a collection, let's remap it
        $array['fields'] = $array['fields'][0];
        $array['entities'] = $array['entities'][0];

        if (array_key_exists('fields_lang', $array)) {
            $array['fields_lang'] = $array['fields_lang'][0];
        }

        return $array;
    }

    public function loadInitialData(string $directory): array
    {
        $entities = [];
        $initialData = $this->parseDirectory($directory);

        foreach ($initialData as $entity => $entityDescription) {
            if (array_key_exists($entity, $entities)) {
                $entities[$entity] = [];
            }

            // if there's no fixtures, $entities is a string
            if (!is_array($entityDescription['entities'])) {
                continue;
            }

            foreach ($entityDescription['entities'][$entity] as $item) {
                $entities[$entity][$item['@id']] = $item;
            }
        }

        return $entities;
    }
}
