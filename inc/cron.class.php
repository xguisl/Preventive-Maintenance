<?php

/**
 * -------------------------------------------------------------------------
 * Plugin de Manutenção Preventiva para GLPI
 * -------------------------------------------------------------------------
 *
 * LICENÇA
 *
 * Este arquivo é parte do Plugin de Manutenção Preventiva.
 *
 * Manutenção Preventiva é um software livre; você pode redistribuí-lo e/ou modificar
 * sob os termos da Licença Pública Geral GNU conforme publicada pela
 * Free Software Foundation; ou versão 2 da Licença, ou
 * (a seu critério) qualquer versão posterior.
 * 
 * Manutenção Preventiva é distribuído na esperança de que seja útil,
 * mas SEM QUALQUER GARANTIA; sem mesmo a garantia implícita de
 * COMERCIALIZAÇÃO ou ADEQUAÇÃO A UM DETERMINADO FIM. Veja o
 * GNU General Public License para mais detalhes.
 *
 * Você deve ter recebido uma cópia da Licença Pública Geral GNU
 * junto com o Manutenção Preventiva. Se não, veja <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2025 William Oliveira Santos / WIDA Work Information Development Analytics
 * @license   GPLv2+ https://www.gnu.org/licenses/gpl-2.0.html
 * @link      [URL do seu plugin ou repositório GitHub]
 * -------------------------------------------------------------------------
 */

/**
 * -------------------------------------------------------------------------
 * Preventive Maintenance plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Preventive Maintenance.
 *
 * Preventive Maintenance is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * Preventive Maintenance is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Preventive Maintenance. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2025 William Oliveira Santos / WIDA Work Information Development Analytics
 * @license   GPLv2+ https://www.gnu.org/licenses/gpl-2.0.html
 * @link      [Your Plugin URL or GitHub Repository]
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginPreventivemaintenanceCron extends CronTask {

   /**
    * Obtém a configuração do plugin
    */
   private static function getPluginConfig($name) {
      global $DB;
      
      $criteria = [
         'SELECT' => ['value'],
         'FROM' => 'glpi_plugin_preventivemaintenance_config',
         'WHERE' => ['name' => $name],
         'LIMIT' => 1
      ];
      
      $iterator = $DB->request($criteria);
      
      if (count($iterator)) {
         $data = $iterator->current();
         return $data['value'];
      }
      
      return false;
   }

   /**
    * Verifica se há tickets de manutenção abertos
    */
   private static function hasOpenMaintenanceTicket($computer_id, $maintenance_name) {
      global $DB;
      
      if (empty($computer_id) || !is_numeric($computer_id) || empty($maintenance_name)) {
         return false;
      }
      
      try {
         // Verifica na tabela de rastreamento de tickets (mais preciso)
         $criteria = [
            'SELECT' => ['id'],
            'FROM' => 'glpi_plugin_preventivemaintenance_tickets',
            'WHERE' => [
               'computer_id' => (int)$computer_id,
               'maintenance_name' => $maintenance_name
            ],
            'LIMIT' => 1
         ];
         
         $iterator = $DB->request($criteria);
         
         if (count($iterator)) {
            return true;
         }
         
         // Verifica tickets GLPI abertos para este computador
         $criteria = [
            'SELECT' => ['glpi_tickets.id'],
            'FROM' => 'glpi_tickets',
            'WHERE' => [
               'glpi_tickets.items_id' => (int)$computer_id,
               'glpi_tickets.itemtype' => 'Computer',
               ['NOT' => ['glpi_tickets.status' => [Ticket::CLOSED, Ticket::SOLVED]]]
            ],
            'LIMIT' => 1
         ];
         
         $iterator = $DB->request($criteria);
         
         return count($iterator) > 0;
      } catch (Exception $e) {
         Toolbox::logError("Erro ao verificar tickets existentes: " . $e->getMessage());
         return false;
      }
   }

   /**
    * Registra ticket de manutenção
    */
   private static function registerMaintenanceTicket($ticket_id, $computer_id, $maintenance_name) {
      global $DB;
      
      try {
         $DB->insert('glpi_plugin_preventivemaintenance_tickets', [
            'ticket_id' => (int)$ticket_id,
            'computer_id' => (int)$computer_id,
            'maintenance_name' => $maintenance_name,
            'date_creation' => date('Y-m-d H:i:s')
         ]);
         return true;
      } catch (Exception $e) {
         Toolbox::logError("Erro ao registrar o ticket: " . $e->getMessage());
         return false;
      }
   }

   /**
    * Cria ticket de manutenção
    */
   private static function createMaintenanceTicket($computer_id, $maintenance_name, $technician_id) {
      if (self::hasOpenMaintenanceTicket($computer_id, $maintenance_name)) {
         return false;
      }

      $ticket = new Ticket();

      // Obtém nome do computador para incluir no título
      $computer = new Computer();
      $computer_name = '';
      if ($computer->getFromDB($computer_id)) {
         $computer_name = $computer->getField('name');
      }

      $input = [
         'name' => sprintf(__('Manutenção Preventiva: %s - %s'), $computer_name, $maintenance_name),
         'content' => sprintf(__('O computador %s requer manutenção preventiva para: %s'), $computer_name, $maintenance_name),
         'type' => Ticket::INCIDENT_TYPE,
         'status' => Ticket::INCOMING,
         'urgency' => 5,
         'impact' => 5,
         'priority' => 5,
         'requesttypes_id' => 1,
         'users_id_recipient' => 0, // Sistema
         'entities_id' => 0, // Root entity
         'date' => date('Y-m-d H:i:s'),
         'items_id' => [
            'Computer' => [(int)$computer_id]
         ]
      ];

      if (!empty($technician_id)) {
         $input['_observers']['_users_id_observer'] = [(int)$technician_id];
      }

      try {
         $ticket_id = $ticket->add($input);
         if ($ticket_id) {
            self::registerMaintenanceTicket($ticket_id, $computer_id, $maintenance_name);
            Toolbox::logInFile('preventivemaintenance', "Ticket criado: $ticket_id para computador $computer_id\n");
            return $ticket_id;
         }
         return false;
      } catch (Exception $e) {
         Toolbox::logError("Erro ao criar o ticket: " . $e->getMessage());
         return false;
      }
   }

   /**
    * Envia notificação por e-mail
    */
   private static function sendEmailNotification($computer_name, $maintenance_name, $next_date, $days_remaining, $comment = '') {
      global $CFG_GLPI, $DB;

      $notification_email = self::getPluginConfig('notification_email');
      if (empty($notification_email)) {
         return false;
      }

      $subject = "Lembrete: Manutenção Preventiva - $computer_name";
      $message = "<h2>Lembrete de Manutenção Preventiva</h2>";
      $message .= "<p><strong>Computador:</strong> $computer_name</p>";
      $message .= "<p><strong>Manutenção:</strong> $maintenance_name</p>";
      $message .= "<p><strong>Data prevista:</strong> " . date('d/m/Y', strtotime($next_date)) . "</p>";
      $message .= "<p><strong>Dias restantes:</strong> $days_remaining</p>";

      if (!empty($comment)) {
         $message .= "<p><strong>Observações:</strong> $comment</p>";
      }

      $message .= "<p><em>Esta é uma notificação automática do plugin de Manutenção Preventiva do GLPI.</em></p>";

      // Usa o sistema de e-mail do GLPI
      try {
         $mail = new GLPINotification();
         $mail->sendMail([
            'to' => $notification_email,
            'subject' => $subject,
            'content' => $message,
            'content_type' => 'html'
         ]);
         Toolbox::logInFile('preventivemaintenance', "Email enviado para $notification_email\n");
         return true;
      } catch (Exception $e) {
         Toolbox::logError("Erro ao enviar email: " . $e->getMessage());
         return false;
      }
   }

   /**
    * Envia notificação via Teams webhook
    */
   private static function sendTeamsNotification($computer_name, $maintenance_name, $next_date, $days_remaining, $comment = '') {
      $webhook_url = self::getPluginConfig('notification_teams_webhook');
      if (empty($webhook_url)) {
         return false;
      }

      $color = '#0078D4';
      if ($days_remaining <= 3) {
         $color = '#FF0000';
      } elseif ($days_remaining <= 7) {
         $color = '#FFA500';
      }

      $card = [
         '@type' => 'MessageCard',
         '@context' => 'https://schema.org/extensions',
         'summary' => 'Lembrete de Manutenção Preventiva',
         'themeColor' => $color,
         'title' => 'Lembrete de Manutenção Preventiva',
         'sections' => [
            [
               'facts' => [
                  [
                     'name' => 'Computador',
                     'value' => $computer_name
                  ],
                  [
                     'name' => 'Manutenção',
                     'value' => $maintenance_name
                  ],
                  [
                     'name' => 'Data prevista',
                     'value' => date('d/m/Y', strtotime($next_date))
                  ],
                  [
                     'name' => 'Dias restantes',
                     'value' => $days_remaining
                  ]
               ]
            ]
         ]
      ];

      if (!empty($comment)) {
         $card['sections'][] = [
            'text' => "**Observações:** $comment"
         ];
      }

      $data = json_encode($card);

      try {
         $ch = curl_init($webhook_url);
         curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
         curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
         curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
         ]);
         curl_setopt($ch, CURLOPT_TIMEOUT, 30);

         $result = curl_exec($ch);
         $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
         curl_close($ch);

         if ($http_code === 200) {
            Toolbox::logInFile('preventivemaintenance', "Teams notification sent for $computer_name\n");
         }

         return $http_code === 200;
      } catch (Exception $e) {
         Toolbox::logError("Erro ao enviar notificação Teams: " . $e->getMessage());
         return false;
      }
   }

   /**
    * Processa criação automática de tickets
    */
   public static function cronAutoTicket() {
      global $DB;

      $auto_ticket = self::getPluginConfig('auto_ticket');
      if ($auto_ticket !== '1') {
         return 0;
      }

      $pm = new PluginPreventivemaintenancePreventivemaintenance();
      $all_maintenances = $pm->find([], 'next_maintenance_date ASC');
      
      $tickets_created = 0;
      
      foreach ($all_maintenances as $item) {
         if (!empty($item['last_maintenance_date']) && !empty($item['next_maintenance_date'])) {
            $last = strtotime($item['last_maintenance_date']);
            $next = strtotime($item['next_maintenance_date']);
            $now = time();
            
            $total_days = $next - $last;
            $elapsed_days = $now - $last;
            
            if ($total_days > 0) {
               $percent = min(100, max(0, round(($elapsed_days / $total_days) * 100)));
               
               if ($percent >= 99) {
                  $ticket_id = self::createMaintenanceTicket(
                     $item['items_id'],
                     $item['name'],
                     $item['technician_id']
                  );
                  
                  if ($ticket_id) {
                     $tickets_created++;
                  }
               }
            }
         }
      }

      Toolbox::logInFile('preventivemaintenance', "Cron AutoTicket: $tickets_created tickets criados\n");
      return $tickets_created;
   }

   /**
    * Processa envio de notificações
    */
   public static function cronSendNotifications() {
      global $DB;

      $notification_enabled = self::getPluginConfig('notification_enabled');
      if ($notification_enabled !== '1') {
         return 0;
      }

      $days_before = (int)self::getPluginConfig('notification_days_before');
      if ($days_before < 1) {
         $days_before = 7;
      }

      $pm = new PluginPreventivemaintenancePreventivemaintenance();
      $all_maintenances = $pm->find([], 'next_maintenance_date ASC');

      $now = time();
      $notifications_sent = 0;
      $notified_today = [];

      foreach ($all_maintenances as $item) {
         if (empty($item['next_maintenance_date'])) {
            continue;
         }

         $next = strtotime($item['next_maintenance_date']);
         $days_remaining = round(($next - $now) / (60 * 60 * 24));

         // Verifica se está dentro do período de notificação
         if ($days_remaining <= $days_before && $days_remaining > 0) {
            // Verifica se já notificou hoje (evita spam)
            $notification_key = $item['id'] . '_' . date('Y-m-d');
            if (isset($notified_today[$notification_key])) {
               continue;
            }

            $computer = new Computer();
            $computer_name = '';
            if ($computer->getFromDB($item['items_id'])) {
               $computer_name = $computer->getName();
            }

            $comment = $item['comment'] ?? '';

            // Envia e-mail
            if (self::sendEmailNotification($computer_name, $item['name'], $item['next_maintenance_date'], $days_remaining, $comment)) {
               $notifications_sent++;
            }

            // Envia Teams
            self::sendTeamsNotification($computer_name, $item['name'], $item['next_maintenance_date'], $days_remaining, $comment);

            $notified_today[$notification_key] = true;
         }
      }

      Toolbox::logInFile('preventivemaintenance', "Cron Notifications: $notifications_sent notificações enviadas\n");
      return $notifications_sent;
   }
}
