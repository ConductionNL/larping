
import React from 'react'

import {getCookie} from "./CookieHandler";

export function getResource(resource, id, state, handler, api, cache = 'force-cache',) {

  let code =  getCookie('token');
  let organization = getCookie('organization');
  if(!code){
    code = state.code
  }
  if(!organization){
    organization = state.organization
  }

  fetch(api + '/' + resource + '/'+ id +'?code='+code + '&organization=' + organization, { cache: cache, credentials: 'same-origin'})
    .then(response => response.json())
    .then(handler)
    .catch()
}

export function getResourceList(resource, state, handler, api,  cache = 'force-cache',) {

  let code =  getCookie('token');
  let organization = getCookie('organization');
  if(!code){
    code = state.code
  }
  if(!organization){
    organization = state.organization
  }

  fetch(api + '/' + resource + '?code='+ code + '&organization=' + organization, { cache: cache, credentials: 'same-origin'})
    .then(response => response.json())
    .then(handler)
    .catch()
}
