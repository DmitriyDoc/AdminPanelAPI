<?php

return [
    'movies_sort_fields' => [
        [
            'value' => 'id',
            'label' => 'ID',
        ],
        [
            'value' => 'id_movie',
            'label' => 'ID фильма',
        ],
        [
            'value' => 'year_release',
            'label' => 'Году',
        ],
        [
            'value' => 'title',
            'label' => 'Названию фильма',
        ],
        [
            'value' => 'created_at',
            'label' => 'Дате создания',
        ],
        [
            'value' => 'updated_at',
            'label' => 'Дате обновления',
        ],
    ],
    'section_sort_fields' => [
        [
            'value' => 'id',
            'label' => 'ID',
        ],
        [
            'value' => 'id_movie',
            'label' => 'ID фильма',
        ],
        [
            'value' => 'year_release',
            'label' => 'Году',
        ],
        [
            'value' => 'collection',
            'label' => 'Коллекции',
        ],
        [
            'value' => 'title',
            'label' => 'Названию фильма',
        ],
        [
            'value' => 'created_at',
            'label' => 'Дате создания',
        ],
        [
            'value' => 'updated_at',
            'label' => 'Дате обновления',
        ],
    ],
    'collection_sort_fields' => [
        [
            'value' => 'id',
            'label' => 'ID',
        ],
        [
            'value' => 'id_movie',
            'label' => 'ID фильма',
        ],
        [
            'value' => 'year_release',
            'label' => 'Году',
        ],
        [
            'value' => 'franchise',
            'label' => 'Франшизе',
        ],
        [
            'value' => 'title',
            'label' => 'Названию фильма',
        ],
        [
            'value' => 'created_at',
            'label' => 'Дате создания',
        ],
        [
            'value' => 'updated_at',
            'label' => 'Дате обновления',
        ],
    ],
    'person_sort_fields' => [
        [
            'value' => 'id',
            'label' => 'ID',
        ],
        [
            'value' => 'id_celeb',
            'label' => 'ID Персоны',
        ],
        [
            'value' => 'nameActor',
            'label' => 'Имени',
        ],
        [
            'value' => 'created_at',
            'label' => 'Дате создания',
        ],
        [
            'value' => 'updated_at',
            'label' => 'Дате обновления',
        ],
    ],
    'tag_sort_fields' => [
        [
            'value' => 'id',
            'label' => 'ID',
        ],
        [
            'value' => 'tag_name_ru',
            'label' => 'Имени тега (Rus)',
        ],
        [
            'value' => 'tag_name_en',
            'label' => 'Имени тега (Eng)',
        ],
        [
            'value' => 'value',
            'label' => 'Ресурсу',
        ],
        [
            'value' => 'created_at',
            'label' => 'Дате создания',
        ],
        [
            'value' => 'updated_at',
            'label' => 'Дате обновления',
        ],
    ],
    'collection_list_info_sort_fields' => [
        [
            'value' => 'id',
            'label' => 'ID',
        ],
        [
            'value' => 'category_id',
            'label' => 'ID Category',
        ],
        [
            'value' => 'label_en',
            'label' => 'Имени коллекции (Rus)',
        ],
        [
            'value' => 'label_ru',
            'label' => 'Имени коллекции (Eng)',
        ],
        [
            'value' => 'value',
            'label' => 'Ресурсу',
        ],
        [
            'value' => 'created_at',
            'label' => 'Дате создания',
        ],
        [
            'value' => 'updated_at',
            'label' => 'Дате обновления',
        ],
    ],
    'franchise_list_info_sort_fields' => [
        [
            'value' => 'id',
            'label' => 'ID',
        ],
        [
            'value' => 'label_en',
            'label' => 'Имени франшизы (Rus)',
        ],
        [
            'value' => 'label_ru',
            'label' => 'Имени франшизы (Eng)',
        ],
        [
            'value' => 'value',
            'label' => 'Ресурсу',
        ],
        [
            'value' => 'created_at',
            'label' => 'Дате создания',
        ],
        [
            'value' => 'updated_at',
            'label' => 'Дате обновления',
        ],
    ],
    'person_filters_fields' => [
        [
            'value' => '?gender=male',
            'label' => 'Мужской пол',
        ],
        [
            'value' => '?gender=female',
            'label' => 'Женский пол',
        ],
        [
            'value' => '?gender=non_binary',
            'label' => 'Небинарный пол',
        ],
        [
            'value' => '?gender=other',
            'label' => 'Другой пол',
        ],
        [
            'value' => '?groups=oscar_best_actress_nominees',
            'label' => 'Номинанты на премию «Оскар» в категории «Лучшая актриса»',
        ],
        [
            'value' => '?groups=oscar_best_actor_nominees',
            'label' => 'Номинанты на премию «Оскар» за лучшую мужскую роль',
        ],
        [
            'value' => '?groups=oscar_best_actress_winners',
            'label' => 'Лауреаты премии «Оскар» за лучшую женскую роль',
        ],
        [
            'value' => '?groups=oscar_best_actor_winners',
            'label' => 'Лауреаты премии «Оскар» за лучшую мужскую роль',
        ],
        [
            'value' => '?groups=oscar_best_supporting_actress_nominees',
            'label' => 'Номинанты на премию «Оскар» в категории «Лучшая актриса второго плана',
        ],
        [
            'value' => '?groups=oscar_best_director_nominees',
            'label' => 'Номинанты на премию «Оскар» за лучшую режиссуру',
        ],
        [
            'value' => '?groups=best_director_winner',
            'label' => 'Победитель в номинации «Лучший режиссер»"',
        ],
        [
            'value' => '?groups=oscar_nominee',
            'label' => 'Номинант на премию «Оскар»',
        ],
        [
            'value' => '?groups=emmy_nominee',
            'label' => 'Номинант на премию «Эмми»',
        ],
        [
            'value' => '?groups=golden_globe_nominated',
            'label' => 'Номинирован на «Золотой глобус»',
        ],
        [
            'value' => '?groups=oscar_winner',
            'label' => 'Обладатель «Оскара»',
        ],
        [
            'value' => '?groups=emmy_winner',
            'label' => 'Обладатель «Эмми»',
        ],
        [
            'value' => '?groups=golden_globe_winning',
            'label' => 'Обладатель «Золотого глобуса»',
        ],
    ]
];
