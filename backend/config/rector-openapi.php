<?php

# php vendor/bin/rector process src --config config/rector-openapi.php
# see also https://github.com/zircote/swagger-php/issues/1047

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\AnnotationToAttributeRector;
use Rector\Php80\ValueObject\AnnotationToAttribute;

return function (RectorConfig $rectorConfig): void {

    $rectorConfig->ruleWithConfiguration(AnnotationToAttributeRector::class, [
        new AnnotationToAttribute('OpenApi\\Annotations\\AdditionalProperties', 'OpenApi\\Attributes\\AdditionalProperties'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Attachable', 'OpenApi\\Attributes\\Attachable'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Components', 'OpenApi\\Attributes\\Components'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Contact', 'OpenApi\\Attributes\\Contact'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Delete', 'OpenApi\\Attributes\\Delete'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Discriminator', 'OpenApi\\Attributes\\Discriminator'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Examples', 'OpenApi\\Attributes\\Examples'),
        new AnnotationToAttribute('OpenApi\\Annotations\\ExternalDocumentation', 'OpenApi\\Attributes\\ExternalDocumentation'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Flow', 'OpenApi\\Attributes\\Flow'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Get', 'OpenApi\\Attributes\\Get'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Head', 'OpenApi\\Attributes\\Head'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Header', 'OpenApi\\Attributes\\Header'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Info', 'OpenApi\\Attributes\\Info'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Items', 'OpenApi\\Attributes\\Items'),
        new AnnotationToAttribute('OpenApi\\Annotations\\JsonContent', 'OpenApi\\Attributes\\JsonContent'),
        new AnnotationToAttribute('OpenApi\\Annotations\\License', 'OpenApi\\Attributes\\License'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Link', 'OpenApi\\Attributes\\Link'),
        new AnnotationToAttribute('OpenApi\\Annotations\\MediaType', 'OpenApi\\Attributes\\MediaType'),
        new AnnotationToAttribute('OpenApi\\Annotations\\OpenApi', 'OpenApi\\Attributes\\OpenApi'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Operation', 'OpenApi\\Attributes\\Operation'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Options', 'OpenApi\\Attributes\\Options'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Parameter', 'OpenApi\\Attributes\\Parameter'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Patch', 'OpenApi\\Attributes\\Patch'),
        new AnnotationToAttribute('OpenApi\\Annotations\\PatchItem', 'OpenApi\\Attributes\\PatchItem'),
        new AnnotationToAttribute('OpenApi\\Annotations\\PathParameter', 'OpenApi\\Attributes\\PathParameter'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Post', 'OpenApi\\Attributes\\Post'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Property', 'OpenApi\\Attributes\\Property'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Put', 'OpenApi\\Attributes\\Put'),
        new AnnotationToAttribute('OpenApi\\Annotations\\RequestBody', 'OpenApi\\Attributes\\RequestBody'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Response', 'OpenApi\\Attributes\\Response'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Schema', 'OpenApi\\Attributes\\Schema'),
        new AnnotationToAttribute('OpenApi\\Annotations\\SecurityScheme', 'OpenApi\\Attributes\\SecurityScheme'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Server', 'OpenApi\\Attributes\\Server'),
        new AnnotationToAttribute('OpenApi\\Annotations\\ServerVariable', 'OpenApi\\Attributes\\ServerVariable'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Tag', 'OpenApi\\Attributes\\Tag'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Trace', 'OpenApi\\Attributes\\Trace'),
        new AnnotationToAttribute('OpenApi\\Annotations\\Xml', 'OpenApi\\Attributes\\Xml'),
        new AnnotationToAttribute('OpenApi\\Annotations\\XmlContent', 'OpenApi\\Attributes\\XmlContent'),
    ]);
};
