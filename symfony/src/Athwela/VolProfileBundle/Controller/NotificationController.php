<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of VolProfileController
 *
 * @author Mampitiya1
 */

namespace Athwela\VolProfileBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
use Athwela\EntityBundle\Entity\Volunteer;
use Athwela\EntityBundle\Entity\Project;
use Athwela\EntityBundle\Entity\Organization;
use Athwela\EntityBundle\Entity\Admin;
use Athwela\DA\CRUD\Read;
use Athwela\DA\CRUD\Create;
use Athwela\DA\CustomQuery\CustomQuery;
use Symfony\Component\HttpFoundation\Request;

class NotificationController extends ContainerAware {

    public function notificationAction($email) {
        $entity = Read::getInstance()->read(new Volunteer(), 'volunteer', 'email', $email);
        $amount = $this->getNotificationCount($entity);
        $projects = $this->getProject($entity);
        $messages = $this->getMessages($entity);
        return $this->container->get('templating')->renderResponse('VolProfileBundle:VolProfile:notification.html.twig', array(
                    'notification' => $amount,
                    'entity' => $entity,
                    'projects' => $projects,
                    'messages' => $messages
        ));
    }

    public function getNotificationCount($entity) {
        $temp[] = 0;

        $notification1 = CustomQuery::getInstance()->customQuery('SELECT count(*) FROM admin_vol_messages where msgStatus = "notRead" and volunteer_ID = ' . $entity->getId());

        while ($row = mysqli_fetch_row($notification1)) {
            $temp[0] = $row[0];
        }
        $notification2 = CustomQuery::getInstance()->customQuery('SELECT count(distinct vp.accepted_at) FROM fos_user, volunteer, volunteer_project as vp where vp.volunteer_ID = ' . $entity->getId() . ' and volunteer.email = fos_user.email and volunteer.ID = ' . $entity->getId() . ' and last_login <= vp.accepted_at and vp.status = "notSeen"');
        while ($row = mysqli_fetch_row($notification2)) {
            $temp[1] = $row[0];
        }
        return $temp;
    }

    public function getNotification($entity) {
        $temp[][] = 0;
        $i = 0;
        $notification = CustomQuery::getInstance()->customQuery('select vp.project_ID, p.organization_ID, vp.accepted_at from fos_user fu, volunteer v, volunteer_project vp, project p where fu.email = v.email and v.ID = vp.volunteer_ID and p.ID = vp.project_ID and vp.accepted_at >= fu.last_login and vp.status = "notSeen" and vp.volunteer_ID = ' . $entity->getId());
        while ($row = mysqli_fetch_row($notification)) {
            for ($index = 0; $index < count($row); $index++) {
                $temp[$i][$index] = $row[$index];
            }
            CustomQuery::getInstance()->customQuery('Update volunteer_project set status = "Seen" where project_ID = ' . $row[0] . ' and volunteer_ID = ' . $entity->getId() . ' and accepted_at = "' . $row[2] . '"');
            $i++;
        }
        return $temp;
    }

    public function getProject($entity) {
        $temp = $this->getNotification($entity);
        $projects[][] = null;
        if ($temp != null && $temp[0][0] != null) {
            for ($i = 0; $i < count($temp); $i++) {
                $projects[$i][0] = Read::getInstance()->read(new Project(), 'project', 'ID', $temp[$i][0]);
                $projects[$i][1] = Read::getInstance()->read(new Organization(), 'organization', 'ID', $temp[$i][1]);
                $projects[$i][2] = $temp[$i][2];
            }
        }
        return $projects;
    }

    public function getMessages($entity) {
        $i = 0;
        $messages[][] = null;

        $message = CustomQuery::getInstance()->customQuery('SELECT * FROM admin_vol_messages where msgStatus = "notRead" and volunteer_ID = ' . $entity->getId());
        
        while ($row = mysqli_fetch_row($message)) {
            for ($index = 0; $index < count($row); $index++) {
                $messages[$i][$index] = $row[$index];
            }            
            CustomQuery::getInstance()->customQuery('Update admin_vol_messages set msgStatus = "Read" where volunteer_ID = ' . $entity->getId() . ' and sent_time = "' . $row[0] . '"');

            $i++;
        }
        return $messages;
    }

    public function messageAction($email) {
        $entity = Read::getInstance()->read(new Volunteer(), 'volunteer', 'email', $email);
        $sent = null;
        return $this->container->get('templating')->renderResponse('VolProfileBundle:VolProfile:message.html.twig', array(
                    'entity' => $entity,
                    'sent' => $sent
        ));
    }

    public function messagesendAction(Request $request, $email) {
        if ($request->getMethod() == "POST") {
            $entity = Read::getInstance()->read(new Volunteer(), 'volunteer', 'email', $email);
            $message[] = null;
            $message[0] = $entity->getId();
            $message[1] = $request->get('message');
            $message[2] = date('Y-m-d h:i:s');
            $message[3] = 'notRead';
            $sent = Create::getInstance()->create($message, 'vol_admin_msg');
            return $this->container->get('templating')->renderResponse('VolProfileBundle:VolProfile:message.html.twig', array('entity' => $entity, 'sent' => $sent));
        }
    }
}
