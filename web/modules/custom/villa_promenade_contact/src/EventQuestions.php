<?php

namespace Drupal\villa_promenade_contact;

/**
 * Questions and choice limits from the private and corporate Tally forms.
 */
final class EventQuestions {

  /**
   * Returns the public event choices, preserving existing stored identifiers.
   */
  public static function eventOptions(): array {
    return ['event1' => 'Privát események', 'event2' => 'Céges események', 'other' => 'Egyéb'];
  }

  /**
   * Returns question definitions in reference order.
   *
   * Sources: https://tally.so/r/gD27Xl and https://tally.so/r/VLgALj.
   */
  public static function definitions(): array {
    return [
      'event1' => [
        'description' => [
          '#type' => 'textarea',
          '#title' => 'Meséljetek pár szóban az eseményetekről',
          '#event_required' => FALSE,
          '#rows' => 4,
        ],
        'guest_count' => [
          '#type' => 'number',
          '#title' => 'Várható létszám:',
          '#event_required' => TRUE,
          '#min' => 1,
          '#step' => 1,
        ],
        'preferred_date' => [
          '#type' => 'date',
          '#title' => 'Mikor ünnepeltek?',
          '#event_required' => FALSE,
        ],
        'arrival_time' => [
          '#type' => 'textfield',
          '#title' => 'Hány órakkór szeretnétek érkezni?',
          '#event_required' => FALSE,
          '#attributes' => ['type' => 'time'],
          '#maxlength' => 5,
        ],
        'date_flexibility' => [
          '#type' => 'radios',
          '#title' => 'Mennyire rugalmas a dátum?',
          '#event_required' => TRUE,
          '#options' => [
            'option_1' => 'Fix dátum',
            'option_2' => '± néhány nap belefér',
            'option_3' => 'Még keressük az ideális időpontot',
          ],
        ],
        'catering' => [
          '#type' => 'checkboxes',
          '#title' => 'Milyen vendéglátást képzeltetek el?',
          '#event_required' => TRUE,
          '#options' => [
            'option_1' => 'Ültetett vacsora – 2 fogás',
            'option_2' => 'Ültetett vacsora – 3 fogás',
            'option_3' => 'Ültetett vacsora – 4 fogás',
            'option_4' => 'Állófogadás / falatkák',
            'option_5' => 'Még nem tudjuk, segítsetek választani',
          ],
        ],
        'drinks' => [
          '#type' => 'checkboxes',
          '#title' => 'Milyen italélményt képzeltetek el?',
          '#event_required' => TRUE,
          '#options' => [
            'option_1' => 'Borpárosítás a vacsorához',
            'option_2' => 'Bor + sommelier által vezetett borpárosítás',
            'option_3' => 'Koktélcsomag',
            'option_4' => 'Alkoholmentes italcsomag',
            'option_5' => 'Korlátlan italcsomag',
            'option_6' => 'Egyedi italkínálatot szeretnénk',
          ],
        ],
        'music' => [
          '#type' => 'checkboxes',
          '#title' => 'Milyen zenei világot képzeltetek el?',
          '#event_required' => TRUE,
          '#options' => [
            'option_1' => 'Élőzene',
            'option_2' => 'DJ',
            'option_3' => 'Csak hangfal bekészítése',
            'option_4' => 'Háttérzene hangfalról és a zenét ránk bízzátok',
            'option_5' => 'Nem szeretnénk zenét',
          ],
        ],
        'technical' => [
          '#type' => 'checkboxes',
          '#title' => 'Szükségetek lesz technikai háttérre?',
          '#event_required' => TRUE,
          '#options' => [
            'option_1' => 'Projektor + vetítővászon',
            'option_2' => 'Mikrofon',
            'option_3' => 'Hangosítás',
            'option_4' => 'Nem lesz rá szükségünk',
            'other' => 'Egyéb',
          ],
          '#has_other' => TRUE,
        ],
        'decoration' => [
          '#type' => 'checkboxes',
          '#title' => 'Mennyire szeretnétek, hogy a Villa segítsen a rendezvény hangulatának kialakításában?',
          '#event_required' => TRUE,
          '#options' => [
            'option_1' => 'Teljes hangulatot szeretnénk ha megterveznétek',
            'option_2' => 'Mi szeretnénk dekorálni',
            'option_3' => 'Nem szeretnénk dekorációt',
            'other' => 'Egyéb',
          ],
          '#has_other' => TRUE,
        ],
        'programme' => [
          '#type' => 'checkboxes',
          '#title' => 'Szeretnétek valamilyen programmal is kiegészíteni az estét?',
          '#event_required' => TRUE,
          '#options' => [
            'option_1' => 'Vezetett borkóstoló',
            'option_2' => 'Kreatív workshop',
            'option_3' => 'Gasztroélmény',
            'option_4' => 'Élő műsor / előadó',
            'option_5' => 'Fotózás',
            'option_6' => 'Egyedi programot szeretnénk',
            'option_7' => 'Nem szeretnénk semmilyen programot',
          ],
        ],
        'requests' => [
          '#type' => 'textarea',
          '#title' => 'Van olyan ötletetek, kívánságotok vagy különleges kérésetek, amire még nem kérdeztünk rá?',
          '#event_required' => FALSE,
          '#rows' => 4,
        ],
      ],
      'event2' => [
        'occasion' => [
          '#type' => 'radios',
          '#title' => 'Milyen eseményt terveztek?',
          '#event_required' => TRUE,
          '#options' => [
            'option_1' => 'Céges vacsora',
            'option_2' => 'Workshop',
            'option_3' => 'Konferencia / Meeting',
            'other' => 'Egyéb',
          ],
          '#has_other' => TRUE,
        ],
        'guest_count' => [
          '#type' => 'number',
          '#title' => 'Várható létszám:',
          '#event_required' => TRUE,
          '#min' => 1,
          '#step' => 1,
        ],
        'duration' => [
          '#type' => 'checkboxes',
          '#title' => 'Az esemény jellege:',
          '#event_required' => TRUE,
          '#options' => [
            'option_1' => 'Fél napos esemény',
            'option_2' => 'Egész napos esemény',
            'option_3' => 'Esti esemény',
            'option_4' => 'Többnapos / egyedi program',
          ],
        ],
        'preferred_date' => [
          '#type' => 'date',
          '#title' => 'Mikor ünnepeltek?',
          '#event_required' => FALSE,
        ],
        'date_flexibility' => [
          '#type' => 'radios',
          '#title' => 'Mennyire rugalmas a dátum?',
          '#event_required' => TRUE,
          '#options' => [
            'option_1' => 'Fix dátum',
            'option_2' => '± néhány nap belefér',
            'option_3' => 'Még keressük az ideális időpontot',
          ],
        ],
        'catering' => [
          '#type' => 'checkboxes',
          '#title' => 'Milyen vendéglátást képzeltetek el?',
          '#event_required' => TRUE,
          '#options' => [
            'option_1' => 'Reggeli ',
            'option_2' => 'Kávészünet – frissítőkkel és falatokkal',
            'option_3' => 'Ültetett ebéd - 2 fogás',
            'option_4' => 'Ültetett vacsora – 2 fogás',
            'option_5' => 'Ültetett vacsora – 3 fogás',
            'option_6' => 'Ültetett vacsora – 4 fogás',
            'option_7' => 'Állófogadás / falatkák',
            'option_8' => 'Többféle megoldást is szeretnénk kombinálni',
            'option_9' => 'Még nem tudjuk, segítsetek választani',
          ],
        ],
        'drinks' => [
          '#type' => 'checkboxes',
          '#title' => 'Milyen italélményt képzeltetek el?',
          '#event_required' => TRUE,
          '#options' => [
            'option_1' => 'Kávé, tea, üdítők',
            'option_2' => 'Borpárosítás a vacsorához',
            'option_3' => 'Bor + sommelier által vezetett borpárosítás',
            'option_4' => 'Koktélcsomag',
            'option_5' => 'Alkoholmentes italcsomag',
            'option_6' => 'Korlátlan italcsomag',
            'option_7' => 'Egyedi italkínálatot szeretnénk',
          ],
        ],
        'technical' => [
          '#type' => 'checkboxes',
          '#title' => 'Szükségetek lesz technikai háttérre?',
          '#event_required' => TRUE,
          '#options' => [
            'option_1' => 'Projektor + vetítővászon',
            'option_2' => 'Mikrofon',
            'option_3' => 'Hangosítás',
            'option_4' => 'Nem lesz rá szükségünk',
            'other' => 'Egyéb',
          ],
          '#has_other' => TRUE,
        ],
        'programme' => [
          '#type' => 'checkboxes',
          '#title' => 'Szeretnétek valamilyen extra programmal kiegészíteni az eseményt?',
          '#event_required' => TRUE,
          '#options' => [
            'option_1' => 'Vezetett borkóstoló',
            'option_2' => 'Gasztroélmény',
            'option_3' => 'Csapatépítő program',
            'option_4' => 'Élőzene',
            'option_5' => 'DJ',
            'option_6' => 'Helyi művész / előadó',
            'option_7' => 'Egyedi programot szeretnénk',
            'option_8' => 'Nem szeretnénk extra programot',
          ],
        ],
        'photography' => [
          '#type' => 'checkboxes',
          '#title' => 'Szeretnétek megörökíteni az eseményt?',
          '#event_required' => TRUE,
          '#options' => [
            'option_1' => 'Fotóst szeretnénk',
            'option_2' => 'Videóst szeretnénk',
            'option_3' => 'Fotó + videó csomagot szeretnénk',
            'option_4' => 'Még nem tudjuk – szívesen kérünk ajánlást',
            'option_5' => 'Nem lesz rá szükségünk',
          ],
        ],
        'requests' => [
          '#type' => 'textarea',
          '#title' => 'Van olyan ötletetek vagy különleges kérésetek, amire még nem kérdeztünk rá?',
          '#event_required' => FALSE,
          '#rows' => 4,
        ],
      ],
      'other' => [
        'description' => [
          '#type' => 'textarea',
          '#title' => 'Mesélj pár szóban az eseményről, hogy megismerjük az elképzelésedet',
          '#event_required' => FALSE,
          '#rows' => 8,
        ],
      ],
    ];
  }

}

