describe('Add a new website activity', () => {
  
  const getIframeForm = () => {
    return cy
    .get('#modal-embeddedform iframe')
    .its('0.contentDocument.body').should('not.be.empty') // retry until the body element is not empty
    .then(cy.wrap)
  }

  beforeEach(() => {
    cy.login(Cypress.env('username'), Cypress.env('password'))
  })

  let siteurl;
  it('create a single site', () => {
    cy.visit('/course/modedit.php?add=website&course=2&section=0')
    
    // Enter website name.
    let websitename = 'Single website ' + Date.now()
    cy.get('input[name=name]').type(websitename)

    // Select distribution.
    cy.get('select[name=distribution]').select(0)

    // Save and Display.
    cy.get('input#id_submitbutton').click()

    // Site homepage is displayed.
    cy.get('h1.title').should('contain', websitename)

    cy.url().then((url) => {
      siteurl = url
    })
  })

  it('change homepage title', () => {
    cy.openforediting(siteurl)

    // Open page form.
    cy.get('a.btn-add-site-page').click()

    getIframeForm().find('#id_title', { timeout: 15000 }).clear().type('Blog')

    getIframeForm().find('input#id_submitbutton').click()

    // New page is displayed.
    cy.get('h1.title').should('contain', 'Blog') 
  })

  it('add a section', () => {
    cy.openforediting(siteurl)

    // Open section form.
    cy.get('a.btn-add-site-section').click()

    // Section title.
    getIframeForm().find('input[name=sectiontitle]', { timeout: 15000 }).type('Welcome to my site')
    
    // Submit
    getIframeForm().find('input#id_submitbutton').click()

    cy.get('h2.section-title').should('contain', 'Welcome to my site')
  })

  it('add a block', () => {
    cy.openforediting(siteurl)

    // Open block form.
    cy.get('a.btn-add-site-block').click()

    getIframeForm().find('#id_contenteditable', { timeout: 15000 }).click().type('Some text')

    getIframeForm().find('input#id_submitbutton').click()

    cy.get('.site-block').should('contain', 'Some text')    
  })

  it('add a page', () => {
    cy.openforediting(siteurl)

    // Open page form.
    cy.get('a.btn-add-site-page').click()

    getIframeForm().find('#id_title', { timeout: 15000 }).type('Helpful resources')

    getIframeForm().find('input#id_submitbutton').click()

    // New page is displayed.
    cy.get('h1.title').should('contain', 'Helpful resources') 
  })
  
})
