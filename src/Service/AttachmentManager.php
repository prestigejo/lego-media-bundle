<?php
declare(strict_types=1);
namespace Idk\LegoMediaBundle\Service;

use Doctrine\ORM\Repository\RepositoryFactory;
use Idk\LegoMediaBundle\Entity\Attachment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Router;


class AttachmentManager{

    private $em;
    private $kernel;
    private $parameters;
    private $router;
    private $attachements;

    public function __construct(EntityManagerInterface $em,  Kernel $kernel, ParameterBagInterface $parameters, Router $router){
        $this->em = $em;
        $this->kernel = $kernel;
        $this->parameters = $parameters;
        $this->router = $router;
        $this->attachements = [];
    }


    protected function getUploadDir(Attachment  $attachment = null): string
    {
        $path = $this->kernel->getProjectDir().'/'.$this->parameters->get('lego.attachment.directory').'/';
        if(!$attachment) return $path;
        return $path . str_replace('\\','-',$attachment->getObjectClass()) . '/'.$attachment->getObjectId().'/';
    }

    public function getAbsolutePath(Attachment $attachment):string{
        return $this->getUploadDir($attachment).$attachment->getPath();
    }

    public function load(string $class, array $ids): void{
        $this->attachements[$class] = [];
        $qb  = $this->getRepository()->createQueryBuilder('a')
            ->where('a.objectId IN (:ids)')
            ->andWhere('a.objectClass = :class')
            ->setParameters(['ids'=>$ids, 'class'=> $class]);
        foreach($qb->getQuery()->execute() as $file){
            if(!isset($this->attachements[$class][$file->getObjectId()])){
                $this->attachements[$class][$file->getObjectId()] = [];
            }
            $this->attachements[$class][$file->getObjectId()][] = $file;
        }
    }

    public function hasList(){
        return $this->parameters->get('lego.attachment.show_list');
    }

    public function deleteById(int $id): void{
        $file = $this->find($id);
        $this->em->remove($file);
        $this->em->flush();
        unlink($this->getAbsolutePath($file));
    }

    public function addFile(UploadedFile $media, $class, $id): ?Attachment{
        if ($media->isValid()) {
            $file = new Attachment();
            $file->setObjectId($id);
            $file->setObjectClass($class);
            $file->setFilename($media->getClientOriginalName());
            $file->setSize($media->getSize());
            $file->setMimeType($media->getMimetype());
            $file->setType($media->getClientOriginalExtension());

            $uploadDir = $this->getUploadDir($file);

            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $name = md5($file->getFilename().$id.microtime()).'.'.$media->getClientOriginalExtension();
            $media->move($uploadDir, $name);

            $file->setPath($name);

            $this->em->persist($file);
            $this->em->flush();
            return $file;
        }
        return null;
    }

    public function getRepository(){
        return $this->em->getRepository(Attachment::class);
    }

    public function find(int $id): ?Attachment{
        return $this->getRepository()->find($id);
    }

    public function findAll(object $entity): iterable{
        $class = $this->getClass($entity);
        $identifier = $this->getIdentifierValue($entity);
        if(isset($this->attachements[$class]) && isset($this->attachements[$class][$identifier])){
            return $this->attachements[$class][$identifier];
        }
        return $this->getRepository()->findBy(['objectClass' => $class, 'objectId' => $identifier]);
    }

    public function getUniqueId(object $entity): string{
        return str_replace('\\','-',get_class($entity)).$this->getIdentifierValue($entity);
    }

    public function getIconByMimeType($mimetype){
        if(strstr($mimetype,'cloud')) {
            return __DIR__ . '/../Resources/public/image/cloud.png';
        }
        return null;
    }

    public function getPublicIcon(Attachment $attachment){
        return $this->router->generate('lego_attachment_thumb',['id' => $attachment->getId()]);
    }

    public function generateRemoveUrl(Attachment $attachment){
        return $this->router->generate('lego_attachment_delete', ['id' => $attachment->getId()]);
    }

    public function isImage(Attachment $attachment):bool{
        return (bool)strstr($attachment->getMimetype(), 'image');
    }


    private function getIdentifierValue(object $entity){
        $meta = $this->em->getClassMetadata(get_class($entity));
        return $meta->getIdentifierValues($entity)[$meta->getSingleIdentifierFieldName()];
    }

    private function getClass(object $entity){
        $meta = $this->em->getClassMetadata(get_class($entity));
        return $meta->getReflectionClass()->getName();
    }



}
