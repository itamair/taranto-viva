((Drupal) => {
  Drupal.behaviors.converse = {
    attach(context, settings) {
      const [chatWrapper] = once('chat_wrapper', '#chat-wrapper', context);
      if (chatWrapper === undefined) {
        return;
      }

      async function search() {
        const results = document.getElementById('response-message');
        const hitsWrapper = document.getElementById('response-hits-wrapper');
        const hits = document.getElementById('response-hits');
        results.innerHTML = 'Thinking...';
        hits.innerHTML = '';
        hitsWrapper.style.display = 'none';

        const text = document.getElementById('chat-input-text').value;
        const model = document.getElementById('chat-select-model').value;

        const url = `${settings.search_api_typesense.url}/multi_search?conversation=true&conversation_model_id=${model}&q=${text}`;
        try {
          const response = await fetch(url, {
            method: 'POST',
            body: `{"searches":[{"collection":"${settings.search_api_typesense.index}","query_by":"embedding","exclude_fields":"embedding,rendered_item","prefix":false}]}`,
            headers: {
              'Content-Type': 'application/json',
              'X-TYPESENSE-API-KEY': settings.search_api_typesense.api_key,
            },
          });
          if (!response.ok) {
            alert('Network response was not ok');
          }

          const json = await response.json();
          console.log(json);

          results.innerHTML = json.conversation.answer;

          let list = '<ul>';

          json.results.forEach(function (result) {
            result.hits.forEach(function (hit) {
              // Extract the chunk ID from the document id
              // (i.e.: entity:node-6:en_39).
              let chunkId = hit.document.id.split(':')[2];
              chunkId = chunkId.split('_')[1];

              list += `<li><a href="/node/${hit.document.nid}" target="_blank">${hit.document.title} (chunk: ${chunkId})</a></li>`;
            });
          });

          list += '</ul>';

          hits.innerHTML = list;
          hitsWrapper.style.display = 'block';
        } catch (error) {
          console.error(error.message);
        }
      }

      const send = document.getElementById('chat-input-send');
      send.addEventListener('click', search);
    },
  };
})(Drupal);
