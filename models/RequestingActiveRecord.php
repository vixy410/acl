<?php

/**
 * RequestingActiveRecord Class File
 *
 * This class serves as the base class for all "actors" in the program who have 
 * to per form actions on other objects 
 * 
 * @author dispy <dispyfree@googlemail.com>
 * @license LGPLv2
 * @package acl.base
 */
class RequestingActiveRecord extends CActiveRecord{
    
    /**
     * Serves as a temporary space for the associated Aro-Object
     * @var AclObject 
     */
    protected $aro = NULL;
       
    /**
     * Loads the associated Aro_Object
     * @throws RuntimeException 
     */
    protected function loadAro(){
        $class = Strategy::getClass('Aro');
        
        if($this->aro === NULL){
            $this->aro = Util::enableCaching($class::model(), 'aroObject')->find('model = :model AND foreign_key = :foreign_key', 
                    array(':model' => get_class($this), 'foreign_key' => $this->getPrimaryKey()));
            
            //If there's no such Aro-Collection... use Guest ^^
            $guest = Strategy::get('guestGroup');
            if(!$this->aro && $guest){
                $this->aro = Util::enableCaching($class::model(), 'aroObject')->find('alias = :alias', array(':alias' => $guest));
                
                //If there's no guest...
                if(!$this->aro)
                    throw new RuntimeException('There is no associated Aro nor a guest-group');
            }
        }
    }
    
    /**
     * Looks up if the user is granted a specific action to the given object
     * @param   string|array    $obj    The object to be checked   
     * @param   string          $action the action to be performed
     * @return bool true if access is granted, false otherwise
     */
    public function may($obj, $action){
        $this->loadAro();
        return $this->aro->may($obj, $action);
    }
    
    /**
     * Grants the object denoted by the $obj-identifier the given actions
     * @param type $obj the object identifier
     * @param array $actions the actions to grant
     * @param bool  $byPassCheck    Whether to bypass the additional grant-check
     * @return bool 
     */
    public function grant($obj, $actions, $byPassCheck = false){
        $this->loadAro();
        
        //If there's no object given, don't assign anything and return true
        if($obj == NULL)
            return true;
        
        return $this->aro->grant($obj, $actions, $byPassCheck);
    }
    
    /**
     * Denies the object denoted by the $obj-identifier the given actions
     * @param type $obj the object identifier
     * @param array $actions the actions to deny
     * @return bool 
     */
    public function deny($obj, $actions){
        $this->loadAro();
        return $this->aro->deny($obj, $actions);
    }
    
    /**
     * This method takes care to associate an ARO-collection with this one
     * 
     * @param CEvent $evt 
     */
    public function afterSave(){
        parent::afterSave();
        if($this->isNewRecord){
            $class = Strategy::getClass('Aro');
            $aro = new $class();
            $aro->model = get_class($this);
            $aro->foreign_key = $this->getPrimaryKey();
            if(!$aro->save())
                throw new RuntimeError("Unable to save Aro-Collection");
        }
    }
    
    /**
     * This method takes care that every associated ACL-objects are properly removed
     */
    public function beforeDelete(){
        //Ok he has the right to do that - remove all the ACL-objects associated with this object
        $class = Strategy::getClass('Aro');
        $aro = $class::model()->find('model = :model AND foreign_key = :key', array(':model' => get_class(          $this), ':key' => $this->getPrimaryKey()));
        
        if(!$aro)
            throw new RuntimeException('No associated Aro-Collection!');
        
        $transaction = Yii::app()->db->beginTransaction();
        try{
            $suc =$aro->delete()&& parent::beforeDelete();
            $transaction->commit();
            return $suc;
        }
        catch(Exception $e){
            $transaction->rollback();
            throw $e;
        }
    }
    
}
?>
