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

  it('creates a student copies', () => {
    cy.visit('/course/modedit.php?add=website&course=2&section=0')
    
    // Enter website name.
    let websitename = 'Student websites ' + Date.now()
    cy.get('input[name=name]').type(websitename)

    // Select distribution.
    cy.get('select[name=distribution]').select(1)

    // Save and Display.
    cy.get('input#id_submitbutton').click()

    // Table with header and at least 1 student.
    cy.get('.mod-website-table-view tr').should('have.length.at.least', 2)

    // Launch the first website.
    cy.get('a.btn-launchsite').first().invoke('removeAttr', 'target').click()
    
    // Site is displayed.
    cy.get('h1.title').should('contain', websitename)

  })

})
